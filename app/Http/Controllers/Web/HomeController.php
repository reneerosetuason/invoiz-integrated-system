<?php
namespace App\Http\Controllers\Web;
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
    if($request->filled('search')) $q->where('name','like','%'.$request->search.'%');
    $sort = $this->sortCatalog($q, $request->input('sort', 'recommended'));
    $products = $q->get();
    $this->attachSale($products);
    $categories = Category::active()
      ->orderByRaw("CASE WHEN name = 'Appliances' THEN 0 ELSE 1 END, name")
      ->get();

    // Storefront stats for the hero band
    $stats = [
      'products' => Product::where('status','active')->count(),
      'sellers' => Product::where('status','active')->distinct()->count('seller_id'),
      'categories' => $categories->count(),
    ];

    // Current buyer's cart snapshot for the hero "Current cart" card
    $cartLines = collect();
    $cartTotal = 0;
    $buyer = $request->session()->get('buyer') ?? (Auth::check() ? ['id' => Auth::id()] : null);
    if($buyer){
      $items = \App\Models\Cart::itemsFor($buyer['id'])->get();
      $prods = Product::whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
      foreach($items as $it){
        $p = $prods->get($it->product_id);
        if(!$p) continue;
        $line = (float)$p->price * (int)$it->quantity;
        $cartTotal += $line;
        $cartLines->push(['name'=>$p->name,'qty'=>(int)$it->quantity,'line'=>$line]);
      }
      $cartLines = $cartLines->take(3);
    }

    $favoriteIds = $buyer ? Favorite::where('buyer_id', $buyer['id'])->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
    return view('home', compact('products','categories','stats','cartLines','cartTotal','buyer','favoriteIds','sort') + ['saleMode' => false]);
  }
  public function show($id){
    $p = Product::with(['category','variants'])->where('status', 'active')->findOrFail($id);
    $p->trackView();
    $p->setRelation('reviews', Review::with('buyer')->where('product_id', $p->id)->where('status', 'visible')->latest()->get());
    $reviewAverage = Review::where('product_id', $p->id)->where('status', 'visible')->avg('rating');
    if ($reviewAverage !== null) $p->rating = $reviewAverage;
    $sellerUserId = \App\Models\Seller::userIdFor((int) $p->seller_id);
    $buyer = request()->session()->get('buyer') ?? (Auth::check() ? ['id' => Auth::id()] : null);
    $isFavorited = $buyer ? Favorite::where('buyer_id', $buyer['id'])->where('product_id', $p->id)->exists() : false;
    $sale = \App\Support\Sales::saleFor($p);
    $salePrice = $sale ? \App\Support\Sales::priceFor($p) : null;
    return view('product', compact('p', 'buyer', 'isFavorited', 'sellerUserId', 'sale', 'salePrice'));
  }

  /** Sale listing — active products discounted vs their compare-at price. */
  public function sale(Request $request){
    $q = $this->catalogQuery($request)
      ->whereNotNull('compare_at_price')
      ->whereColumn('compare_at_price', '>', 'price');
    if($request->filled('category')) $q->where('category_id', $request->integer('category'));
    if($request->filled('search')) $q->where('name','like','%'.$request->search.'%');
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

    $cartLines = collect();
    $cartTotal = 0;
    $buyer = $request->session()->get('buyer') ?? (Auth::check() ? ['id' => Auth::id()] : null);
    if($buyer){
      $items = \App\Models\Cart::itemsFor($buyer['id'])->get();
      $prods = Product::whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
      foreach($items as $it){
        $p = $prods->get($it->product_id);
        if(!$p) continue;
        $line = \App\Support\Sales::priceFor($p) * (int)$it->quantity;
        $cartTotal += $line;
        $cartLines->push(['name'=>$p->name,'qty'=>(int)$it->quantity,'line'=>$line]);
      }
      $cartLines = $cartLines->take(3);
    }

    $favoriteIds = $buyer ? Favorite::where('buyer_id', $buyer['id'])->pluck('product_id')->map(fn ($id) => (int) $id)->all() : [];
    return view('home', [
      'products'=>$products,'categories'=>$categories,'stats'=>$stats,
      'cartLines'=>$cartLines,'cartTotal'=>$cartTotal,'buyer'=>$buyer,'favoriteIds'=>$favoriteIds,
      'saleMode'=>true,'sort'=>$sort,
    ]);
  }

  private function catalogQuery(Request $request)
  {
    return Product::with('category')->where('status','active')
      ->select('products.*')
      ->selectSub(Review::query()->selectRaw('AVG(rating)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_rating')
      ->selectSub(Review::query()->selectRaw('COUNT(*)')->whereColumn('reviews.product_id', 'products.id')->where('status', 'visible'), 'visible_reviews_count');
  }

  private function attachSale($products): void
  {
    foreach ($products as $product) {
      $sale = \App\Support\Sales::saleFor($product);
      $effective = \App\Support\Sales::priceFor($product);
      $product->setAttribute('on_sale', (bool) $sale);
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
