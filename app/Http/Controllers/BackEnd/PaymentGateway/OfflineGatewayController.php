<?php

namespace App\Http\Controllers\BackEnd\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway\OfflineGateway;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;

class OfflineGatewayController extends Controller
{
  public function index()
  {
    $offlineGateways = OfflineGateway::orderBy('id', 'desc')->get();

    return view('backend.payment-gateways.offline-gateways.index', compact('offlineGateways'));
  }

  public function store(Request $request)
  {
    $rules = [
      'name' => 'required|string|max:255',
      'short_description' => 'nullable|string|max:1000',
      'instructions' => 'nullable|string|max:10000',
      'has_attachment' => 'required|in:0,1',
      'serial_number' => 'required|integer|min:0|max:16777215'
    ];

    $message = [
      'has_attachment.required' => 'The attachment field is required.'
    ];

    $validator = Validator::make($request->all(), $rules, $message);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    OfflineGateway::create($this->configurationPayload($request) + ['status' => 0]);

    Session::flash('success', 'Added successfully. Review the configuration, then activate the payment method explicitly.');

    return Response::json(['status' => 'success'], 200);
  }

  public function updateStatus(Request $request, $id)
  {
    $validated = $request->validate([
      'status' => ['required', 'in:0,1'],
    ]);

    try {
      $offlineGateway = OfflineGateway::query()->findOrFail((int) $id);
      $offlineGateway->update(['status' => (int) $validated['status']]);

      Session::flash('success', 'Updated Successfully');
    } catch (ModelNotFoundException $e) {
      Session::flash('warning', 'No record found in database!');
    }

    return redirect()->back();
  }

  public function update(Request $request)
  {
    $rules = [
      'id' => 'required|integer|exists:offline_gateways,id',
      'name' => 'required|string|max:255',
      'short_description' => 'nullable|string|max:1000',
      'instructions' => 'nullable|string|max:10000',
      'has_attachment' => 'required|in:0,1',
      'serial_number' => 'required|integer|min:0|max:16777215'
    ];

    $message = [
      'has_attachment.required' => 'The attachment field is required.'
    ];

    $validator = Validator::make($request->all(), $rules, $message);

    if ($validator->fails()) {
      return Response::json([
        'errors' => $validator->getMessageBag()->toArray()
      ], 400);
    }

    try {
      $gateway = OfflineGateway::query()->findOrFail((int) $request->id);
      $gateway->update($this->configurationPayload($request));

      Session::flash('success', 'Updated Successfully');
    } catch (ModelNotFoundException $e) {
      Session::flash('warning', 'No record found in database!');
    }

    return Response::json(['status' => 'success'], 200);
  }

  public function destroy($id)
  {
    try {
      OfflineGateway::query()->findOrFail((int) $id)->delete();

      return redirect()->back()->with('success', 'Deleted Successfully');
    } catch (ModelNotFoundException $e) {
      return redirect()->back()->with('warning', 'No record found in database!');
    }
  }

  private function configurationPayload(Request $request): array
  {
    return [
      'name' => trim((string) $request->input('name')),
      'short_description' => trim((string) $request->input('short_description')) ?: null,
      'instructions' => Purifier::clean((string) $request->input('instructions')),
      'has_attachment' => (int) $request->input('has_attachment'),
      'serial_number' => (int) $request->input('serial_number'),
    ];
  }
}
