<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shopify\Clients\Rest;
use Shopify\Clients\Graphql;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $client = new Rest(env("STORE_URL"), env("SHOPIFY_ACCESS_TOKEN"));
        $response = $client->get(path: 'products');
        $products = $response->getBody();
        Log::info('Prodyc API Response:', [$products]);
        return response()->json($products);
    }

    public function store(Request $request)
    {
        try {
            $client = new Rest(env("STORE_URL"), env("SHOPIFY_ACCESS_TOKEN"));
            $response = $client->post(path: 'products', body: $request->all());
            $product = $response->getBody();
            $productData = json_decode($product, true);
            return response()->json($productData, 201);
        } catch (\Exception $e) {
            Log::error('Exception in Product Creation:', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'error' => 'An unexpected error occurred while creating the product',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function getVariant(Request $request)
    {
        $sku = $request->input('sku');

        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }

        // Build GraphQL query
        $query = <<<GQL
        {
            productVariants(first: 10, query: "sku:$sku") {
                edges {
                    node {
                        id
                        title
                        sku
                        price
                        inventoryQuantity
                        product {
                            id
                            title
                        }
                    }
                }
            }
        }
        GQL;

        // Create GraphQL client using shop and access token from .env
        $client = new Graphql(env('STORE_URL'), env('SHOPIFY_ACCESS_TOKEN'));

        // Send the query
        $response = $client->query([
            'query' => $query,
        ]);

        $data = $response->getDecodedBody();
        Log::info('GraphQL Response:', [$data]);
        $variantNode = $data['data']['productVariants']['edges'][0]['node'] ?? null;

        if (!$variantNode) {
            return response()->json(['error' => 'Variant not found'], 404);
        }

        Log::info('Variant found for SKU:', [$variantNode]);

        return response()->json($variantNode);
    }
}
