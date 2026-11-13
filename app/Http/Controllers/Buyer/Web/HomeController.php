<?php
namespace App\Http\Controllers\Buyer\Web;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Favorite;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
class HomeController extends Controller {
  public function index(Request $request){
    $q = $this->catalogQuery($request);
    if($request->filled('category')) $q->where('category_id', $request->integer('category'));

    $matchingStores = collect();
    if($request->filled('search')) {
      $term = trim((string) $request->input('search'));
      $q->where(function ($sq) use ($term) {
        $sq->where('products.name', 'like', '%'.$term.'%')
           ->orWhere('products.description', 'like', '%'.$term.'%')
           ->orWhere('products.brand', 'like', '%'.$term.'%')
           ->orWhereHas('seller', function ($sel) use ($term) {
             $sel->where('store_name', 'like', '%'.$term.'%')
                 ->orWhere('business_name', 'like', '%'.$term.'%')
                 ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$term.'%'));
           });
      });

      try {
        $matchingStores = \App\Models\Seller::approved()
          ->with('user')
          ->where(function ($sq) use ($term) {
            $sq->where('store_name', 'like', '%'.$term.'%')
               ->orWhere('business_name', 'like', '%'.$term.'%')
               ->orWhere('line_of_business', 'like', '%'.$term.'%')
               ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$term.'%'));
          })
          ->take(4)
          ->get()
          ->map(function ($store) {
            $uid = (int) $store->user_id;
            $catalogIds = array_values(array_unique(array_filter([(int) $store->id, $uid])));
            $productsCount = Product::storefront()->whereIn('seller_id', $catalogIds)->count();
            $productIds = Product::whereIn('seller_id', $catalogIds)->pluck('id');
            $rating = null;
            try {
              if (class_exists(\App\Models\Review::class)) {
                $rating = \App\Models\Review::whereIn('product_id', $productIds)->where('status', 'visible')->avg('rating');
              }
            } catch (\Throwable $e) {}
            if ($rating === null) {
              $rating = Product::whereIn('id', $productIds)->avg('rating');
            }

            return [
              'id' => $store->id,
              'user_id' => $uid,
              'name' => $store->display_name,
              'line' => $store->line_of_business ?: 'Verified Seller',
              'city' => $store->city ?: ($store->user?->city ?? ''),
              'logo' => $store->logoUrl(),
              'products_count' => $productsCount,
              'rating' => $rating ? round((float) $rating, 1) : null,
              'url' => url('/store/' . $uid),
            ];
          });
      } catch (\Throwable $e) {
        $matchingStores = collect();
      }
    }
    $sort = $this->sortCatalog($q, $request->input('sort', 'recommended'));
    $products = $q->get();
    $this->attachSale($products);
    if ($request->boolean('on_sale')) {
      $products = $products->filter(fn ($p) => $p->on_sale)->values();
    }
    $categories = Category::active()
      ->orderByRaw("CASE WHEN name = 'Appliances' THEN 0 ELSE 1 END, name")
      ->get();

    // Storefront stats for the hero band
    $stats = [
      'products' => Product::where('status','active')->count(),
      'sellers' => Product::where('status','active')->distinct()->count('seller_id'),
      'categories' => $categories->count(),
    ];

    $buyer = \App\Support\BuyerSession::current();
    [$cartLines, $cartTotal] = $this->cartPreview($buyer);

    $favoriteIds = $buyer ? Favorite::where('buyer_id', $buyer['id'])->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
    return view('buyer.home', compact('products','categories','stats','cartLines','cartTotal','buyer','favoriteIds','sort','matchingStores') + ['saleMode' => false]);
  }
  public function show($id){
    $p = Product::with(['category','variants','images'])->storefront()->findOrFail($id);
    $p->trackView();
    $p->setRelation('reviews', Review::with('buyer')->where('product_id', $p->id)->where('status', 'visible')->latest()->get());
    $reviewAverage = Review::where('product_id', $p->id)->where('status', 'visible')->avg('rating');
    if ($reviewAverage !== null) $p->rating = $reviewAverage;
    $sellerUserId = \App\Models\Seller::userIdFor((int) $p->seller_id);
    $buyer = \App\Support\BuyerSession::current();
    $isFavorited = $buyer ? Favorite::where('buyer_id', $buyer['id'])->where('product_id', $p->id)->exists() : false;
    $sale = \App\Support\Sales::saleFor($p);
    $salePrice = $sale ? \App\Support\Sales::priceFor($p) : null;
    return view('buyer.product', compact('p', 'buyer', 'isFavorited', 'sellerUserId', 'sale', 'salePrice'));
  }

  /** Sale listing — active products discounted vs their compare-at price. */
  public function sale(Request $request){
    $q = \App\Support\Sales::scopeDiscounted($this->catalogQuery($request));
    if($request->filled('category')) $q->where('category_id', $request->integer('category'));
    if($request->filled('search')) {
      $term = trim((string) $request->input('search'));
      $q->where(function ($sq) use ($term) {
        $sq->where('products.name', 'like', '%'.$term.'%')
           ->orWhere('products.description', 'like', '%'.$term.'%')
           ->orWhere('products.brand', 'like', '%'.$term.'%')
           ->orWhereHas('seller', function ($sel) use ($term) {
             $sel->where('store_name', 'like', '%'.$term.'%')
                 ->orWhere('business_name', 'like', '%'.$term.'%')
                 ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$term.'%'));
           });
      });
    }
    $sort = $this->sortCatalog($q, $request->input('sort', 'recommended'));
    $products = $q->get();
    $this->attachSale($products);
    $categories = Category::active()
      ->orderByRaw("CASE WHEN name = 'Appliances' THEN 0 ELSE 1 END, name")
      ->get();

    $stats = [
      'products' => Product::where('status','active')->count(),
      'sellers' => Product::where('status','active')->distinct()->count('seller_id'),
      'categories' => $categories->count(),
    ];

    $buyer = \App\Support\BuyerSession::current();
    [$cartLines, $cartTotal] = $this->cartPreview($buyer);

    $favoriteIds = $buyer ? Favorite::where('buyer_id', $buyer['id'])->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
    return view('buyer.home', [
      'products'=>$products,'categories'=>$categories,'stats'=>$stats,
      'cartLines'=>$cartLines,'cartTotal'=>$cartTotal,'buyer'=>$buyer,'favoriteIds'=>$favoriteIds,
      'saleMode'=>true,'sort'=>$sort,
    ]);
  }

  private function cartPreview(?array $buyer): array
  {
    $lines = $buyer ? \App\Models\Cart::linesFor($buyer['id']) : collect();
    return [$lines->take(3)->map(fn ($line) => [
      'name' => $line['product']->name, 'qty' => $line['qty'], 'line' => $line['line'],
    ])->values(), (float) $lines->sum('line')];
  }

  private function catalogQuery(Request $request)
  {
    return Product::with('category')->storefront()
      ->select('products.*')
      ->selectSub(Review::query()->selectRaw('AVG(rating)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_rating')
      ->selectSub(Review::query()->selectRaw('COUNT(*)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_reviews_count');
  }

  private function attachSale($products): void
  {
    foreach ($products as $product) {
      $sale = \App\Support\Sales::saleFor($product);
      $effective = \App\Support\Sales::priceFor($product);
      $product->setAttribute('on_sale', \App\Support\Sales::isOnSale($product));
      $product->setAttribute('sale_price', $sale ? $effective : null);
      $product->setAttribute('effective_price', $effective);
    }
  }

  private function sortCatalog($query, string $sort): string
  {
    $allowed = ['recommended', 'newest', 'price_asc', 'price_desc', 'rating', 'most_reviewed'];
    if (! in_array($sort, $allowed, true)) $sort = 'recommended';
    switch ($sort) {
      case 'price_asc': $query->orderBy('price')->orderByDesc('created_at'); break;
      case 'price_desc': $query->orderByDesc('price')->orderByDesc('created_at'); break;
      case 'rating': $query->orderByRaw('COALESCE(visible_rating, rating, 0) DESC')->orderByDesc('visible_reviews_count')->orderByDesc('created_at'); break;
      case 'most_reviewed': $query->orderByDesc('visible_reviews_count')->orderByRaw('COALESCE(visible_rating, rating, 0) DESC')->orderByDesc('created_at'); break;
      case 'newest': $query->orderByDesc('created_at'); break;
      default: $query->orderByRaw("CASE WHEN category_id = (SELECT id FROM categories WHERE name = 'Appliances' LIMIT 1) THEN 0 ELSE 1 END, created_at DESC");
    }
    return $sort;
  }
}
