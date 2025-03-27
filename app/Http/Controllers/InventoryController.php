<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Shopify\Clients\Graphql;
use Shopify\Clients\Rest;
use Illuminate\Support\Facades\Http;

class InventoryController extends Controller
{
    public function store(Request $request)
    {
        if (!$request->has('sku') || empty($request->sku) || !$request->has('quantity') || empty($request->quantity)) {
            return response()->json([
                'success' => false,
                'message' => 'SKU/Quantity is required'
            ], 400);
        }

        try {
            $sku = $request->sku;
            $client = new Rest(env("STORE_URL"), env("SHOPIFY_ACCESS_TOKEN"));
            $variantInventoryID = $this->getInventoryItemId($sku);
            $cleanedInventoryItemId = str_replace('gid://shopify/InventoryItem/', '', $variantInventoryID);
            $quantity = (int) $request->quantity;
            $formattedQuantity = ($quantity < 0 ? '-' : '+') . abs($quantity);
            $body = [
                'inventory_item_id' => (int) $cleanedInventoryItemId,
                'location_id' => env("LOCATION_ID"),
                'available_adjustment' => $formattedQuantity
            ];
            $response = $client->post(path: 'inventory_levels/adjust.json', body: $body);
            $responseBody = $response->getBody();
            $jsonResponseBody = json_decode($responseBody, true);
            return response()->json([$jsonResponseBody, $body], 201);
        } catch (\Exception $e) {
            Log::error("Error fetching inventory item ID: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the inventory item ID.'
            ], 500);
        }
    }

    public function setAbsoluteValue(Request $request)
    {
        if (!$request->has('sku') || empty($request->sku) || !$request->has('quantity') || empty($request->quantity)) {
            return response()->json([
                'success' => false,
                'message' => 'SKU/Quantity is required'
            ], 400);
        }
        try {
            $sku = $request->sku;
            $client = new Rest(env("STORE_URL"), env("SHOPIFY_ACCESS_TOKEN"));
            $variantInventoryID = $this->getInventoryItemId($sku);
            $cleanedInventoryItemId = str_replace('gid://shopify/InventoryItem/', '', $variantInventoryID);
            $quantity = (int) $request->quantity;
            // $formattedQuantity = ($quantity < 0 ? '-' : '+') . abs($quantity);
            $body = [
                'inventory_item_id' => (int) $cleanedInventoryItemId,
                'location_id' => env("LOCATION_ID"),
                'available' => $quantity
            ];
            $response = $client->post(path: 'inventory_levels/set.json', body: $body);
            $responseBody = $response->getBody();
            $jsonResponseBody = json_decode($responseBody, true);
            return response()->json([$jsonResponseBody, $body], 201);
        } catch (\Exception $e) {
            Log::error("Error fetching inventory item ID: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving the inventory item ID.'
            ], 500);
        }
    }


    private function getInventoryItemId($sku)
    {
        $client = new Graphql(env("STORE_URL"), env("SHOPIFY_ACCESS_TOKEN"));
        $query = <<<GQL
        {
            productVariants(first: 1, query: "sku:$sku") {
                edges {
                    node {
                        inventoryItem {
                            id
                        }
                    }
                }
            }
        }
        GQL;
        $response = $client->query($query, ['sku' => $sku]);
        $variant = $response->getDecodedBody();
        Log::info('Shopify Response for Inventory Item ID:', [$variant]);
        $inventoryItemId = $variant['data']['productVariants']['edges'][0]['node']['inventoryItem']['id'] ?? null;
        return $inventoryItemId;
    }
}
