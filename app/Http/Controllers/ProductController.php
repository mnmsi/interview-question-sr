<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\Variant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function index()
    {
        $variants = Variant::all();
        $products = Product::with('product_variant', 'product_variant_price')
            ->paginate(5);

        return view('products.index', compact('products', 'variants'));
    }

    public function search(Request $request)
    {
        $title = $request->title;
        $variant = $request->variant;
        $price_from = $request->price_from;
        $price_to = $request->price_to;
        $date = $request->date;

        $variants = Variant::all();

        $products = Product::with('product_variant', 'product_variant_price')
            ->when($title, function ($q, $title) {
                $q->where('title', 'LIKE', "%" . $title . "%");
            })
            ->when($date, function ($q, $date) {
                $date = Carbon::parse($date)->format('Y-m-d');
                $q->whereBetween('created_at', [$date . " 00:00:00", $date . " 23:59:59"]);
            })
            ->when($variant, function ($q, $variant) {
                $q->whereHas('product_variant', function ($q) use ($variant) {
                    $q->where('variant_id', $variant);
                });
            })
            ->when($variant, function ($q, $variant) {
                $q->whereHas('product_variant', function ($q) use ($variant) {
                    $q->where('variant_id', $variant);
                });
            })
            ->where(function ($q) use ($price_from, $price_to) {
                if (!empty($price_from) && !empty($price_to)) {
                    $q->whereHas('product_variant_price', function ($q) use ($price_from, $price_to) {
                        $q->whereBetween('price', [$price_from, $price_to]);
                    });
                }
            })
            ->paginate(5);

        return view('products.index', compact('products', 'variants'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Http\Response|\Illuminate\View\View
     */
    public function create()
    {
        $variants = Variant::all();

        return view('products.create', compact('variants'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $storeProd = Product::create($request->all());

            if ($storeProd) {
                if (isset($request->product_variant)) {
                    $varientData['variant_id'] = $request->product_variant[0]['option'];
                    $varientData['product_id'] = $storeProd->id;
                    foreach ($request->product_variant[0]['tags'] as $varient) {
                        $varientData['variant'] = $varient;
                        ProductVariant::create($varientData);
                    }
                }
            }

            if (isset($request->product_variant_prices)) {
                $varientPrice['product_id'] = $storeProd->id;
                foreach ($request->product_variant_prices as $vp) {
                    $varientPrice['price'] = $vp['price'];
                    $varientPrice['stock'] = $vp['stock'];
                    ProductVariantPrice::create($varientPrice);
                }
            }

            if ($request->hasFile('product_image')) {
                $prodImg['file_path'] = Storage::putFile('prod_image', $request->file('product_image'));
                $prodImg['product_id'] = $storeProd->id;
                ProductImage::create($prodImg);
            }

            DB::commit();
            return response()->json([
                'status' => true,
                'msg' => "Success!!"
            ]);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'msg' => $exception->getMessage()
            ]);
        }
    }


    /**
     * Display the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function show($product)
    {

    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        $variants = Variant::all();
        return view('products.edit', compact('variants', 'product'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        try {
            $updateProd = $product->update($request->all());

            if ($updateProd) {
                ProductVariant::where('product_id', $product->id)->delete();
                if (isset($request->product_variant)) {
                    foreach ($request->product_variant as $varient) {
                        $varientData['variant'] = implode(' / ', $varient['tags']);
                        $varientData['variant_id'] = $varient['option'];
                        $varientData['product_id'] = $product->id;
                        $varient = ProductVariant::create($varientData);
                    }
                }
            }

            if (isset($request->product_variant_prices)) {
                ProductVariantPrice::where('product_id', $product->id)->delete();
                foreach ($request->product_variant_prices as $varientPrice) {
                    $varientPrice['product_id'] = $product->id;
                    ProductVariantPrice::create($varientPrice);
                }
            }

            DB::commit();
            return response()->json([
                'status' => true,
                'msg' => "Success!!"
            ]);
        } catch (\Exception $exception) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'msg' => $exception->getMessage()
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Models\Product $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        //
    }
}
