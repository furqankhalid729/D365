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
        if ($order) {
            $token = $this->getMicrosoftToken();
            $apiData = [
                '_request' => [
                    'DataAreaId' => 'GC',
                    'SalesOrderHeader' => [
                        'MessageId' => "67e5b9f4ab173",
                        'OrderStatus' => 'Invoiced',
                        'paymentStatus' => 'received',
                        'fulfillmentStatus' => 'delivered'
                    ]
                ]
            ];
            $response = Http::withToken($token)->post(env("D365_UPDATE_ORDER"), $apiData);
            return $response;
        }
    }

    private function saveOrder($shopifyOrder, $order, $email)
    {
        $orderArray = $order->getData(true);
        Order::create([
            'orderId' => $shopifyOrder['id'],
            'D365_ID' => $orderArray['order']['_request']['SalesOrderHeader']['MessageId'],
            'email' => $email,
            'orderName' => $shopifyOrder["name"]
        ]);
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
                "DiscountPercentage" => 10,
                "Discount" => $item["price"] * 0.1,
                "UnitPrice" => $item["price"],
                "LineAmount" => $item["quantity"] * $item["price"]
            ];
        }, $shopifyOrder["line_items"], array_keys($shopifyOrder["line_items"]));
        $customer = Customer::where("email", $email)->first();
        $apiData = [
            "_request" => [
                "DataAreaId" => "GC",
                "SalesOrderHeader" => [
                    "MessageId" => uniqid(),
                    "SalesOrderNumber" => $shopifyOrder["name"],
                    "CustomerAccountNumber" => $customer->crmId,
                    "DlvTerm" => "30 days",
                    "RequestedReceiptDate" => date("m/d/Y"),
                    "DlvMode" => "ship",
                    "PaymMode" => "COD",
                    "OrderStatus" => "Created",
                    "paymentStatus" => "Not received",
                    "fulfillmentStatus" => "Not delivered"
                ],
                "SalesOrderLines" => $salesOrderLines
            ]
        ];
        $response = Http::withToken($token)->post(env("D365_CREATE_ORDER"), $apiData);
        if ($response->successful()) {
            Log::info('D365 Order Created Successfully:', $apiData);
            return response()->json(['message' => 'Order sent to D365', 'order' => $apiData, 'response'=>$response->body()], 201);
        } else {
            Log::error('D365 Order Creation Failed:', ['error' => $response->body()]);
            return response()->json(['message' => 'Failed to create order in D365', 'error' => $response->body()], $response->status());
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
}
