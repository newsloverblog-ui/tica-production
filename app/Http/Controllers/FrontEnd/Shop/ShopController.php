<?php

namespace App\Http\Controllers\FrontEnd\Shop;

use App\Http\Controllers\Controller;
use App\Models\BasicSettings\Basic;
use App\Models\PaymentGateway\OfflineGateway;
use App\Models\PaymentGateway\OnlineGateway;
use App\Models\ShopManagement\Product;
use App\Models\ShopManagement\ProductCategory;
use App\Models\ShopManagement\ProductImage;
use App\Models\ShopManagement\ProductReview;
use App\Models\ShopManagement\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Services\PaymentGatewayPolicy;
use App\Services\ShopCheckoutPricingService;

class ShopController extends Controller
{
  public function index(Request $request)
  {
    $basic = Basic::select('shop_status')->first();
    if ($basic->shop_status == 0) {
      return redirect()->route('index');
    }
    $language = $this->getLanguage();
    $information  = [];
    $product_categories = ProductCategory::where('status', 1)->where('language_id', $language->id)->get();

    $category = $search = $min = $max = $product_short = null;
    if ($request->filled('category')) {
      $category = $request['category'];
      $category = ProductCategory::where('slug', $category)->first();
      $category = $category->id;
    }
    if ($request->filled('search')) {
      $search = $request['search'];
    }
    if ($request->filled('min') && $request->filled('max')) {
      $min = $request['min'];
      $max = $request['max'];
    }
    if ($request->filled('product_short')) {
      if ($request['product_short'] == 'new') {
        $order_by_column = 'products.id';
        $order = 'desc';
      } elseif ($request['product_short'] == 'default') {
        $order_by_column = 'products.id';
        $order = 'desc';
      } elseif ($request['product_short'] == 'old') {
        $order_by_column = 'products.id';
        $order = 'asc';
      } elseif ($request['product_short'] == 'hight-to-low') {
        $order_by_column = 'products.current_price';
        $order = 'desc';
      } elseif ($request['product_short'] == 'low-to-high') {
        $order_by_column = 'products.current_price';
        $order = 'asc';
      }
    } else {
      $order_by_column = 'products.id';
      $order = 'desc';
    }

    $products = Product::join('product_contents', 'product_contents.product_id', '=', 'products.id')
      ->join('product_categories', 'product_categories.id', '=', 'product_contents.category_id')
      ->where('product_contents.language_id', '=', $language->id)
      ->when($category, function ($query, $category) {
        return $query->where('product_contents.category_id', '=', $category);
      })
      ->when($search, function ($query, $keyword) {
        return $query->where('product_contents.title', 'like', '%' . $keyword . '%');
      })
      ->when(($min && $max), function ($query) use ($min, $max) {
        return $query->where('products.current_price', '>=', $min)->where('products.current_price', '<=', $max);
      })
      ->where('products.status', 1)
      ->select('products.*', 'product_contents.id as productInfoId', 'product_contents.title', 'product_contents.slug', 'product_categories.name as category')
      ->orderBy($order_by_column, $order)
      ->paginate(9);
    $information['products'] = $products;
    $information['product_categories'] = $product_categories;

    $max = Product::max('current_price');
    $min = Product::min('current_price');
    $information['max'] = $max;
    $information['min'] = $min;


    return view('frontend.shop.index', $information);
  }
  //details
  public function details($slug, $id)
  {
    $basic = Basic::select('shop_status')->first();
    if ($basic->shop_status == 0) {
      return redirect()->route('index');
    }
    $language = $this->getLanguage();
    $information = [];
    $product = Product::join('product_contents', 'product_contents.product_id', '=', 'products.id')
      ->join('product_categories', 'product_categories.id', '=', 'product_contents.category_id')
      ->where('product_contents.language_id', '=', $language->id)
      ->select('products.*', 'product_contents.id as productInfoId', 'product_contents.title', 'product_contents.description', 'product_contents.summary', 'product_contents.meta_keywords', 'product_contents.meta_description', 'product_contents.slug',  'product_categories.name as category', 'product_categories.slug as slug')
      ->where('products.id', $id)
      ->firstOrFail();
    $information['product'] = $product;
    $product_gallery = ProductImage::where('product_id', $id)->get();
    $information['galleries'] = $product_gallery;
    return view('frontend.shop.details', $information);
  }
  public function addToCart(Request $request, $id)
  {
    $validated = $request->validate([
      'qty' => ['nullable', 'integer', 'min:1', 'max:100'],
    ]);

    return $this->appendProductToCart((int) $id, (int) ($validated['qty'] ?? 1));
  }

  public function addToCart2(Request $request, $id)
  {
    $validated = $request->validate([
      'qty' => ['required', 'integer', 'min:1', 'max:100'],
    ]);

    return $this->appendProductToCart((int) $id, (int) $validated['qty']);
  }

  //cart
  public function cart()
  {
    $basic = Basic::select('shop_status')->first();
    if ($basic->shop_status == 0) {
      return redirect()->route('index');
    }
    $this->invalidateCheckoutQuote();
    $cart_items = Session::get('cart');
    return view('frontend.shop.cart', compact('cart_items'));
  }
  public function cartitemremove($id)
  {
    $cart = (array) Session::get('cart', []);
    $id = (int) $id;

    if (array_key_exists($id, $cart)) {
      unset($cart[$id]);
      $this->storeCart($cart);
    }

    [$total, $count] = $this->cartTotals($cart);

    return response()->json([
      'message' => 'Product removed successfully',
      'count' => $count,
      'total' => $total,
    ]);
  }

  public function updatecart(Request $request)
  {
    $validated = $request->validate([
      'product_id' => ['required', 'array', 'min:1', 'max:100'],
      'product_id.*' => ['required', 'integer', 'min:1', 'distinct'],
      'qty' => ['required', 'array', 'min:1', 'max:100'],
      'qty.*' => ['required', 'integer', 'min:1', 'max:100'],
    ]);

    if (count($validated['product_id']) !== count($validated['qty'])) {
      throw \Illuminate\Validation\ValidationException::withMessages([
        'qty' => 'Every cart item must have exactly one quantity.',
      ]);
    }

    $cart = (array) Session::get('cart', []);
    if ($cart === []) {
      return response()->json(['error' => 'Your cart is empty.'], 422);
    }

    $language = $this->getLanguage();
    foreach ($validated['product_id'] as $key => $id) {
      $id = (int) $id;
      if (!array_key_exists($id, $cart)) {
        throw \Illuminate\Validation\ValidationException::withMessages([
          'product_id' => "Product {$id} is not in this cart.",
        ]);
      }

      $product = $this->cartProduct($id, (int) $language->id);
      if (!$product) {
        throw \Illuminate\Validation\ValidationException::withMessages([
          'product_id' => "Product {$id} is no longer available.",
        ]);
      }

      $quantity = (int) $validated['qty'][$key];
      if ($product->type !== 'digital' && (int) $product->stock < $quantity) {
        return response()->json(['error' => $product->title . ' stock not available'], 422);
      }

      $cart[$id]['qty'] = $quantity;
    }

    $this->storeCart($cart);
    [$total, $count] = $this->cartTotals($cart);

    return response()->json(['message' => 'Cart Update Successfully.', 'total' => $total, 'count' => $count]);
  }

  //orderNow
  public function orderNow(Request $request)
  {
    $validated = $request->validate([
      'product_id' => ['required', 'integer', 'min:1'],
      'quantity' => ['required', 'integer', 'min:1', 'max:100'],
    ]);

    $id = (int) $validated['product_id'];
    $quantity = (int) $validated['quantity'];
    $language = $this->getLanguage();
    $product = $this->cartProduct($id, (int) $language->id);
    abort_if(!$product, 404);

    $cart = (array) Session::get('cart', []);
    $newQuantity = (int) ($cart[$id]['qty'] ?? 0) + $quantity;

    if ($product->type !== 'digital' && (int) $product->stock < $newQuantity) {
      return back()->with(['message' => 'Out of Stock!', 'alert-type' => 'error']);
    }

    $cart[$id] = [
      'name' => $product->title,
      'qty' => $newQuantity,
      'price' => $product->current_price,
      'photo' => $product->feature_image,
      'type' => $product->type,
    ];
    $this->storeCart($cart);

    return redirect()->route('shopping.cart');
  }
  //checkout
  public function checkout()
  {
    $basic = Basic::select('shop_status', 'shop_guest_checkout', 'shop_tax')->first();
    if ($basic->shop_status == 0) {
      return redirect()->route('index');
    }
    $gatewayPolicy = app(PaymentGatewayPolicy::class);
    $online_gateways = OnlineGateway::where('status', 1)
      ->get()
      ->filter(fn (OnlineGateway $gateway) => $gatewayPolicy->canOfferShopOnline((string) $gateway->keyword))
      ->values();
    $offline_gateways = OfflineGateway::where('status', 1)
      ->orderBy('serial_number', 'asc')
      ->get();

    $cart = Session::get('cart');
    if ($cart == null) {
      $notification = ['message' => 'Your Cart is Empty!', 'alert-type' => 'error'];

      return back()->with($notification);
    }

    if (Auth::guard('customer')->check()) {
      return view('frontend.shop.checkout', compact('online_gateways', 'offline_gateways'));
    }

    if ((int) $basic->shop_guest_checkout === 1) {
      return view('frontend.shop.checkout', compact('online_gateways', 'offline_gateways') + ['type' => 'guest']);
    }

    return redirect()->route('customer.login', ['redirectPath' => 'checkout']);
  }
  public function applyCoupon(Request $request, ShopCheckoutPricingService $pricing)
  {
    $validated = $request->validate([
      'coupon_code' => ['required', 'string', 'max:100'],
    ]);

    try {
      $quote = $pricing->quote(
        (array) Session::get('cart', []),
        (string) $validated['coupon_code']
      );
    } catch (\DomainException $exception) {
      Session::forget(['Shop_discount', 'Shop_coupon_code']);

      return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
    }

    Session::put('cart', $quote['cart']);
    Session::put('Shop_discount', $quote['discount']);
    Session::put('Shop_coupon_code', $quote['coupon_code']);

    return response()->json([
      'status' => 'success',
      'message' => 'Coupon applied successfully',
      'discount' => $quote['discount'],
    ]);
  }
  //buy
  public function buy(Request $request)
  {
  }

  private function cartProduct(int $productId, int $languageId): ?Product
  {
    return Product::query()
      ->join('product_contents', 'product_contents.product_id', '=', 'products.id')
      ->where('products.id', $productId)
      ->where('products.status', 1)
      ->where('product_contents.language_id', $languageId)
      ->select('products.*', 'product_contents.title')
      ->first();
  }

  private function appendProductToCart(int $productId, int $quantity)
  {
    abort_if($productId < 1, 404);

    $language = $this->getLanguage();
    abort_if(!$language, 404);
    $product = $this->cartProduct($productId, (int) $language->id);
    abort_if(!$product, 404);

    $cart = (array) Session::get('cart', []);
    $existingQuantity = filter_var($cart[$productId]['qty'] ?? 0, FILTER_VALIDATE_INT);
    if ($existingQuantity === false || $existingQuantity < 0 || $existingQuantity > 100) {
      return response()->json(['error' => 'The cart contains an invalid quantity.'], 422);
    }

    $newQuantity = $existingQuantity + $quantity;
    if ($newQuantity > 100) {
      return response()->json(['error' => 'A product quantity cannot exceed 100.'], 422);
    }
    if (strtolower((string) $product->type) !== 'digital' && (int) $product->stock < $newQuantity) {
      return response()->json(['error' => 'Out of Stock'], 422);
    }

    $cart[$productId] = [
      'name' => $product->title,
      'qty' => $newQuantity,
      'price' => $product->current_price,
      'photo' => $product->feature_image,
      'type' => $product->type,
    ];
    $this->storeCart($cart);

    return response()->json(['message' => 'Product added to cart successfully!']);
  }

  /**
   * Persist a cart mutation and invalidate every value derived from the
   * previous cart contents. Coupons are re-applied explicitly by the buyer.
   */
  private function storeCart(array $cart): void
  {
    Session::put('cart', $cart);
    $this->invalidateCheckoutQuote();
  }

  private function invalidateCheckoutQuote(): void
  {
    Session::forget(['Shop_discount', 'Shop_coupon_code', 'shipping_cost']);
  }

  private function cartTotals(array $cart): array
  {
    $total = 0.0;
    $count = 0;

    foreach ($cart as $item) {
      $quantity = max(0, (int) ($item['qty'] ?? 0));
      $price = max(0, (float) ($item['price'] ?? 0));
      $total += $price * $quantity;
      $count += $quantity;
    }

    return [round($total, 2), $count];
  }

  //review
  public function review(Request $request)
  {
    $validated = $request->validate([
      'product_id' => ['required', 'integer', 'exists:products,id'],
      'review' => ['nullable', 'required_without:comment', 'integer', 'between:1,5'],
      'comment' => ['nullable', 'required_without:review', 'string', 'max:2000'],
    ]);

    $customerId = (int) Auth::guard('customer')->id();
    $productId = (int) $validated['product_id'];

    $isActiveProduct = Product::query()->whereKey($productId)->where('status', 1)->exists();
    $isVerifiedPurchase = OrderItem::query()
      ->join('product_orders', 'product_orders.id', '=', 'order_items.product_order_id')
      ->where('order_items.user_id', $customerId)
      ->where('order_items.product_id', $productId)
      ->where('product_orders.payment_status', 'completed')
      ->where(function ($query): void {
        $query->whereNull('product_orders.refund_status')
          ->orWhere('product_orders.refund_status', '!=', 'processed');
      })
      ->exists();

    if (!$isActiveProduct || !$isVerifiedPurchase) {
      return back()->with('error', 'Only verified purchasers can review this product.');
    }

    $productReview = ProductReview::firstOrNew([
      'user_id' => $customerId,
      'product_id' => $productId,
    ]);
    if (array_key_exists('review', $validated) && $validated['review'] !== null) {
      $productReview->review = $validated['review'];
    }
    if (array_key_exists('comment', $validated) && $validated['comment'] !== null) {
      $productReview->comment = $validated['comment'];
    }
    $productReview->save();

    return back()->with('success', $productReview->wasRecentlyCreated
      ? 'Review submitted successfully.'
      : 'Review updated successfully.');
  }
}
