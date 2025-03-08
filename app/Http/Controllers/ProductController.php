<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shopify\Clients\Rest;

class ProductController extends Controller
{
    public function index(Request $request){
        $client = new Rest(env("STORE_URL"), env("SHOPIFY_ACCESS_TOKEN"));
        $response = $client->get(path: 'products');
        $products = $response->getBody(); 
        Log::info('Prodyc API Response:', [$products]);
        return response()->json($products);
    }

    public function store(Request $request){
        try{
            $client = new Rest(env("STORE_URL"), env("SHOPIFY_ACCESS_TOKEN"));
            $response = $client->post(path: 'products', body: $request->all());
            $product = $response->getBody(); 
            $productData = json_decode($product, true);
            return response()->json($productData, 201);
        }
        catch(\Exception $e) {
            Log::error('Exception in Product Creation:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'An unexpected error occurred while creating the product',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
