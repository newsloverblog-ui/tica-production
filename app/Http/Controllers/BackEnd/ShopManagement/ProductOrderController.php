<?php

namespace App\Http\Controllers\BackEnd\ShopManagement;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\BasicSettings\MailTemplate;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\ShopManagement\OrderItem;
use App\Models\ShopManagement\ProductOrder;
use PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use App\Services\AuditLogger;
use App\Services\DataExportService;
use App\Services\ProductOrderInventoryService;
use App\Services\ProductOrderDocumentStorage;
use App\Services\ManualProductOrderPaymentService;
use App\Services\ManualPaymentDecisionNotificationService;
use App\Services\PaidProductOrderFulfillmentService;
use App\Services\ApplicationMailService;
use App\Services\ProductOrderExportQueryService;
use DomainException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;

class ProductOrderController extends Controller
{
  //index
  public function index(Request $request)
  {
    $type = $status = $order_id =  null;
    if ($request->filled('type')) {
      $type = $request['type'];
    }
    if ($request->filled('status')) {
      $status = $request['status'];
    }
    if ($request->filled('order_id')) {
      $order_id = $request['order_id'];
    }

    $orders = ProductOrder::with(['payment_intent', 'gateway_refund'])->whereNull('archived_at')->when($type, function ($query, $type) {
      return $query->where('order_status', '=', $type);
    })
      ->when($status, function ($query, $status) {
        return $query->where('payment_status', '=', $status);
      })
      ->when($order_id, function ($query, $order_id) {
        return $query->where('order_number', 'like', '%' . $order_id . '%');
      })
      ->orderByDesc('id')
      ->paginate(10);

    return view('backend.product.order.index', compact('orders'));
  }
  //delete'
  public function delete(Request $request, $id)
  {
    DB::transaction(function () use ($id): void {
      $order = ProductOrder::whereKey($id)->lockForUpdate()->firstOrFail();
      if ($order->archived_at) {
        return;
      }
      $order->forceFill([
        'archived_at' => now(),
        'archived_by' => Auth::guard('admin')->id(),
      ])->save();
      AuditLogger::record('product_order.archived', $order, ['archived_at' => null], [
        'archived_at' => $order->archived_at?->toIso8601String(),
      ]);
    }, 3);

    return redirect()->back()->with('success', 'Order archived successfully. Financial and item history was preserved.');
  }
  //bulk_delete
  public function bulk_delete(Request $request)
  {
    $validated = $request->validate([
      'ids' => ['required', 'array', 'min:1', 'max:100'],
      'ids.*' => ['required', 'integer', 'distinct'],
    ]);
    $ids = array_values(array_unique(array_map('intval', $validated['ids'])));
    sort($ids);

    DB::transaction(function () use ($ids): void {
      $orders = ProductOrder::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
      foreach ($orders as $order) {
        if ($order->archived_at) {
          continue;
        }
        $order->forceFill([
          'archived_at' => now(),
          'archived_by' => Auth::guard('admin')->id(),
        ])->save();
        AuditLogger::record('product_order.archived', $order, ['archived_at' => null], [
          'archived_at' => $order->archived_at?->toIso8601String(),
        ], ['bulk' => true]);
      }
    }, 3);
    Session::flash('success', 'Orders archived successfully. Financial and item history was preserved.');

    return response()->json(['status' => 'success'], 200);
  }
  //updateStatus
  public function updateStatus(
    Request $request,
    $id,
    ManualProductOrderPaymentService $payments,
    ManualPaymentDecisionNotificationService $notifications
  )
  {
    $validated = $request->validate([
      'payment_status' => ['required', 'in:completed,rejected'],
    ]);
    $requestedStatus = $validated['payment_status'];

    try {
      $result = $payments->transition((int) $id, $requestedStatus);
    } catch (\DomainException $exception) {
      throw ValidationException::withMessages(['payment_status' => $exception->getMessage()]);
    }

    $order = $result->order;

    if ($requestedStatus === 'completed') {
      app(PaidProductOrderFulfillmentService::class)->dispatch($order->id);
    } else if ($requestedStatus === 'rejected' && $result->changed) {
      try {
        $notifications->dispatchProductOrderRejection((int) $order->id);
        Session::flash('success', 'Order rejected. Customer notification was queued.');
      } catch (\Throwable $exception) {
        report($exception);
        Session::flash('warning', 'Order rejected. Customer notification will be retried automatically.');
      }
    }

    return redirect()->back();
  }

  //updateOrderStatus
  public function updateOrderStatus(Request $request, $id)
  {
    $validated = $request->validate([
      'order_status' => ['required', 'in:pending,processing,rejected,completed'],
    ]);
    $requestedStatus = $validated['order_status'];

    $order = DB::transaction(function () use ($id, $requestedStatus) {
      $order = ProductOrder::whereKey($id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
      $before = strtolower((string) $order->order_status);

      if ($requestedStatus === 'rejected' && strtolower((string) $order->payment_status) === 'completed') {
        throw ValidationException::withMessages([
          'order_status' => 'A paid order cannot be rejected without a controlled refund or cancellation workflow.',
        ]);
      }
      if ($before === 'completed' && $requestedStatus !== 'completed') {
        throw ValidationException::withMessages([
          'order_status' => 'A fulfilled order cannot be moved back to an earlier state.',
        ]);
      }

      if ($before !== $requestedStatus) {
        $order->forceFill(['order_status' => $requestedStatus])->save();
        AuditLogger::record('product_order.status_changed', $order,
          ['order_status' => $before], ['order_status' => $requestedStatus]);
      }

      if ($requestedStatus === 'rejected') {
        app(ProductOrderInventoryService::class)->release($order, 'order_rejected');
      }

      return $order;
    }, 3);

    if ($requestedStatus === 'processing') {
      $this->sendOrderMail($order, 'Order is Processing Now');
    } elseif ($requestedStatus === 'rejected') {
      $this->sendOrderMail($order, 'Order was Rejected');
    } elseif ($requestedStatus === 'completed') {
      $this->sendOrderMail($order, 'Order is completed');
    }

    return redirect()->back();
  }

  public function sendOrderMail($orderInfo, $title)
  {
    // first get the mail template info from db
    $mailTemplate = MailTemplate::where('mail_type', 'product_shipping')->first();
    $mailSubject = $mailTemplate->mail_subject;
    $mailBody = $mailTemplate->mail_body;

    // second get the website title & mail's smtp info from db
    $info = DB::table('basic_settings')
      ->select('website_title')
      ->first();

    $customerName = $orderInfo->billing_fname . ' ' . $orderInfo->billing_lname;
    $orderId = $orderInfo->order_number;
    $orderStatus = $orderInfo->order_status;

    $websiteTitle = $info->website_title;

    $mailBody = str_replace('{customer_name}', $customerName, $mailBody);
    $mailBody = str_replace('{order_id}', $orderId, $mailBody);
    $mailBody = str_replace('{status}', $orderStatus, $mailBody);
    $mailBody = str_replace('{website_title}', $websiteTitle, $mailBody);

    try {
      app(ApplicationMailService::class)->queueHtml(
        'product_order_status', $orderInfo->billing_email, $mailSubject, $mailBody, [], null, $orderInfo
      );

      return;
    } catch (\Exception $e) {
      report($e);
      return Session::flash('warning', 'The order was updated, but its email could not be queued.');
    }
  }

  public function generateInvoice($orderInfo)
  {
    $width = "50%";
    $float = "right";
    $mb = "35px";
    $ml = "18px";

    $contents = PDF::loadView(
      'frontend.shop.approve_invoice',
      compact('orderInfo', 'width', 'float', 'mb', 'ml')
    )->output();

    return app(ProductOrderDocumentStorage::class)->storeInvoice($orderInfo, $contents);
  }

  public function invoice($id)
  {
    $order = ProductOrder::whereKey($id)->firstOrFail();
    AuditLogger::record('product_order.invoice_viewed', $order);
    return app(ProductOrderDocumentStorage::class)->response($order, 'invoice', 'inline');
  }

  public function receipt($id)
  {
    $order = ProductOrder::whereKey($id)->firstOrFail();
    AuditLogger::record('product_order.receipt_viewed', $order);
    return app(ProductOrderDocumentStorage::class)->response($order, 'receipt', 'inline');
  }
  //details
  public function details($id)
  {
    $order = ProductOrder::with('order_items')->where('id', $id)->firstOrFail();
    return view('backend.product.order.details', compact('order'));
  }

  public function report(Request $request)
  {
    $filters = $request->validate([
      'from_date' => ['nullable', 'date_format:Y-m-d'],
      'to_date' => ['nullable', 'date_format:Y-m-d'],
      'payment_method' => ['nullable', 'string', 'max:100'],
      'payment_status' => ['nullable', 'in:pending,completed,refunded,rejected'],
      'order_status' => ['nullable', 'in:pending,processing,completed,rejected'],
    ]);

    if (!empty($filters['from_date']) && !empty($filters['to_date'])) {
      $filters['max_order_id'] = (int) ProductOrder::query()->whereNull('archived_at')->max('id');
      $data['orders'] = app(ProductOrderExportQueryService::class)
        ->query($filters)->orderByDesc('id')->paginate(10)->withQueryString();
    } else {
      $data['orders'] = collect();
    }


    $data['onPms'] = OnlineGateway::where('status', 1)->get();
    $data['offPms'] = OfflineGateway::where('status', 1)->get();
    $data['abs'] = Basic::select('base_currency_symbol_position', 'base_currency_symbol')->first();


    return view('backend.product.order.report', $data);
  }

  //export
  public function export(Request $request, DataExportService $exports)
  {
    $validated = $request->validate([
      'from_date' => ['required', 'date_format:Y-m-d'],
      'to_date' => ['required', 'date_format:Y-m-d'],
      'payment_method' => ['nullable', 'string', 'max:100'],
      'payment_status' => ['nullable', 'in:pending,completed,refunded,rejected'],
      'order_status' => ['nullable', 'in:pending,processing,completed,rejected'],
      'format' => ['required', 'in:csv,xlsx'],
    ]);

    try {
      $exports->queueProductOrders((int) Auth::guard('admin')->id(), $validated, $validated['format']);
    } catch (DomainException $exception) {
      return back()->withInput()->with('warning', $exception->getMessage());
    }

    return redirect()->route('admin.product_order_exports.index')
      ->with('success', 'Product-order export queued. The private download will appear when processing finishes.');
  }
}
