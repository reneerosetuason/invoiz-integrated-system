<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
class HomeController extends Controller {
  public function index(Request $request){
    $q = Product::with('category')->where('status','active');
    if($request->filled('category')) $q->where('category_id', $request->integer('category'));
    if($request->filled('search')) $q->where('name','like','%'.$request->search.'%');
    $products = $q->orderByRaw("CASE WHEN category_id = (SELECT id FROM categories WHERE name = 'Appliances' LIMIT 1) THEN 0 ELSE 1 END, created_at DESC")->get();
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
    $buyer = $request->session()->get('buyer');
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

    return view('home', compact('products','categories','stats','cartLines','cartTotal','buyer') + ['saleMode' => false]);
  }
  public function show($id){
    $p = Product::with(['category','variants'])->findOrFail($id);
    return view('product', ['p'=>$p]);
  }

  /** Sale listing — active products discounted vs their compare-at price. */
  public function sale(Request $request){
    $q = Product::with('category')->where('status','active')
      ->whereNotNull('compare_at_price')
      ->whereColumn('compare_at_price', '>', 'price');
    if($request->filled('category')) $q->where('category_id', $request->integer('category'));
    if($request->filled('search')) $q->where('name','like','%'.$request->search.'%');
    $products = $q->orderBy('created_at','desc')->get();
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
    $buyer = $request->session()->get('buyer');
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

    return view('home', [
      'products'=>$products,'categories'=>$categories,'stats'=>$stats,
      'cartLines'=>$cartLines,'cartTotal'=>$cartTotal,'buyer'=>$buyer,
      'saleMode'=>true,
    ]);
  }
}
