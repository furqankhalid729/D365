<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Settings;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        Log::info("Order Data", $request->all());
        $data = $request->all();
        $email = $data['email'] ?? null;
        $shippingAddress = $data['shipping_address'] ?? null;

        $token = $this->getMicrosoftToken();
        info("Token is", [$token]);
        if ($token == "")
            return response()->json([
                "message" => "error generating token"
            ]);

        $customer = Customer::where("email", $email)->first();
        if (!$customer)
            $customerID = $this->createCustomer($shippingAddress, $email, $token);

        $checkOrder = Order::where("orderId", $data["id"])->first();
        if ($checkOrder) {
            return response()->json([
                "message" => "Order already exists"
            ]);
        }
        $order = $this->createOrder($request->all(), $token, $email);
        $this->saveOrder($request->all(), $order, $email);
        return $order;
    }

    public function update(Request $request)
    {
        Log::info("Order ID", [$request["id"]]);
        $order = Order::where("orderId", $request["id"])->first();
        $data = $request->all();
        if ($order) {
            Log::info("Order Data", $data);
            Log::info("Order status", [$data['fulfillment_status']]);
            if($data['fulfillment_status'] == "fulfilled"){
                $token = $this->getMicrosoftToken();
                $apiData = [
                    '_request' => [
                        'DataAreaId' => 'GC',
                        'SalesOrderHeader' => [
                            'MessageId' => $data['id'],
                            'OrderStatus' => 'Invoiced',
                            'paymentStatus' => 'received',
                            'fulfillmentStatus' => 'delivered'
                        ]
                    ]
                ];
                $response = Http::withToken($token)->post(env("D365_UPDATE_ORDER"), $apiData);
                return $response;
            }
            if ($data['fulfillment_status'] == "cancelled") {
                $token = $this->getMicrosoftToken();
                $apiData = [
                    '_request' => [
                        'DataAreaId' => 'GC',
                        'SalesOrderHeader' => [
                            'MessageId' => $data['id'],
                            'OrderStatus' => 'Cancelled'
                        ]
                    ]
                ];
                $response = Http::withToken($token)->post(env("D365_UPDATE_ORDER"), $apiData);
                return $response;
            }
        }
        else{
            return response()->json([
                "message" => "Order not found"
            ]);
        }
    }

    private function saveOrder($shopifyOrder, $order, $email)
    {
        $orderArray = $order->getData(true);

        if ($orderArray['status'] == "success") {
            Log::info("Order Array", [$orderArray['response']]);
            Order::create([
                'orderId' => $shopifyOrder['id'],
                'D365_ID' => $orderArray['order']['_request']['SalesOrderHeader']['MessageId'],
                'email' => $email,
                'orderName' => $shopifyOrder["name"],
                'status' => $orderArray['status'],
                'payload' => json_encode($orderArray['order'])
            ]);
        } else {
            Log::info("Order Array", [$orderArray]);
            Order::create([
                'orderId' => $shopifyOrder['id'],
                'D365_ID' => $orderArray['order']['_request']['SalesOrderHeader']['MessageId'],
                'email' => $email,
                'orderName' => $shopifyOrder["name"],
                'status' => $orderArray['status'],
                'payload' => json_encode($orderArray['order'])
            ]);
        }
    }

    private function createCustomer($shippingAddress, $email, $token)
    {
        $CustomerID = "CUS-" . date("YmdHis");
        $apiData = [
            "_request" => [
                "DataAreaId" => "GC",
                "Customer" => [
                    "Name" => $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name'],
                    "CustType" => "Person",
                    "city" => $shippingAddress['city'],
                    "Street" => $shippingAddress['address1'],
                    "state" => $shippingAddress['province'],
                    "county" => $shippingAddress['country'],
                    "email" => $email,
                    "Phone" => $shippingAddress['phone'] ?? "+971555555555",
                    "country" => "UAE"
                ]
            ]
        ];
        $response = Http::withToken($token)
            ->post(env("D365_CREATE_CUSTOMER_ACCOUNT"), $apiData);

        if ($response->successful()) {
            $responseData = $response->json();
            $customer = new Customer();
            $customer->crmId = $responseData["custID"];
            $customer->name = $shippingAddress['first_name'] . ' ' . $shippingAddress['last_name'];
            $customer->email = $email;
            $customer->save();

            return response()->json([
                'message' => 'Customer created successfully',
                'customer' => $customer
            ], 201);
        } else {
            return response()->json([
                'message' => 'Failed to create customer',
                'error' => $response->body()
            ], $response->status());
        }

        Log::info("API Response", $response->json());
    }

    private function createOrder($shopifyOrder, $token, $email)
    {
        $salesOrderLines = array_map(function ($item, $index) {
            return [
                "LineNumberExternal" => (string) ($index + 1),
                "ItemNumber" => $item["sku"],
                "SalesQuantity" => $item["quantity"],
                "Discount" => $item["total_discount_set"]['presentment_money']['amount'],
                "UnitPrice" => $item["price"],
                "LineAmount" => $item["quantity"] * $item["price"]
            ];
        }, $shopifyOrder["line_items"], array_keys($shopifyOrder["line_items"]));
        $customer = Customer::where("email", $email)->first();
        $gatewayNames = $shopifyOrder['payment_gateway_names'] ?? [];
        $firstGateway = $gatewayNames[0] ?? '';
        log::info("Payment Gateway", [$firstGateway, $gatewayNames]);
        if (
            str_contains($firstGateway, 'Cash on Delivery') ||
            str_contains($firstGateway, 'COD') ||
            str_contains($firstGateway, 'Manual')
        ) {
            $paymentMethod = 'COD';
        } else {
            $paymentMethod = 'CreditCard';
        }

        $apiData = [
            "_request" => [
                "DataAreaId" => "GC",
                "SalesOrderHeader" => [
                    "MessageId" => (string) $shopifyOrder['id'],
                    "SalesOrderNumber" => $shopifyOrder["name"],
                    "CustomerAccountNumber" => $customer->crmId,
                    "DlvTerm" => "30 days",
                    "RequestedReceiptDate" => date("m/d/Y"),
                    "DlvMode" => "ship",
                    "PaymMode" => $paymentMethod,
                    "OrderStatus" => "Created",
                    "paymentStatus" => "Not received",
                    "fulfillmentStatus" => "Not delivered",
                    "shippingCost" => $shopifyOrder["current_shipping_price_set"]['presentment_money']['amount'],
                ],
                "SalesOrderLines" => $salesOrderLines
            ]
        ];
        $response = Http::withToken($token)->post(env("D365_CREATE_ORDER"), $apiData);
        if ($response->successful()) {
            $responseData = json_decode($response->body(), true);
            $success = isset($responseData['Success']) && $responseData['Success'] === true;
            $status = $success ? 'success' : 'error';
            if ($success) {
                Log::info('D365 Order Created Successfully:', $apiData);
                return response()->json([
                    'message' => 'Order sent to D365',
                    'order' => $apiData,
                    'response' => $responseData,
                    'status' => $status
                ], 201);
            } else {
                Log::error('D365 Order Creation Failed:', ['error' => $responseData]);
                return response()->json([
                    'message' => 'Failed to create order in D365',
                    'error' => $responseData,
                    'order' => $apiData,
                    'status' => $status
                ], $response->status());
            }
        } else {
            Log::error('D365 Order Creation Failed:', ['error' => $response->body()]);
            return response()->json(['message' => 'Failed to create order in D365', 'order' => $apiData, 'response' => $response->body(), 'status' => 'error'], $response->status());
        }
    }

    private function getMicrosoftToken()
    {
        if (Cache::has('microsoft_access_token')) {
            return Cache::get('microsoft_access_token');
        }
        $settings = Settings::where("id", 1)->first();
        $response = Http::asForm()->post(env("D365_GET_TOKEN_URL"), [
            'client_id' => $settings->client_id,
            'client_secret' => $settings->client_secret,
            'scope' => $settings->scope,
            'grant_type' => $settings->grant_type,
        ]);

        if ($response->successful()) {
            $token = $response->json()['access_token'];
            $expiresIn = 50 * 60;
            Cache::put('microsoft_access_token', $token, $expiresIn);
            return $token;
        }
        return '';
    }

    public function resendOrder($id)
    {
        $order = Order::where("id", $id)->first();
        if ($order) {
            $token = $this->getMicrosoftToken();
            $apiData = json_decode($order->payload);
            $response = Http::withToken($token)->post(env("D365_CREATE_ORDER"), $apiData);
            if ($response->successful()) {
                $responseData = json_decode($response->body(), true);
                $success = isset($responseData['Success']) && $responseData['Success'] === true;
                $status = $success ? 'success' : 'error';
                if ($success) {
                    Log::info('D365 Order Created Successfully:', $apiData);
                    return response()->json([
                        'message' => 'Order sent to D365',
                        'order' => $apiData,
                        'response' => $responseData,
                        'status' => $status
                    ], 201);
                } else {
                    Log::error('D365 Order Creation Failed:', ['error' => $responseData]);
                    return response()->json([
                        'message' => 'Failed to create order in D365',
                        'error' => $responseData,
                        'order' => $apiData,
                        'status' => $status
                    ], $response->status());
                }
            } else {
                Log::error('D365 Order Creation Failed:', ['error' => $response->body()]);
                return response()->json(['message' => 'Failed to create order in D365', 'order' => $apiData, 'response' => $response->body(), 'status' => 'error'], $response->status());
            }
        }
    }
}
