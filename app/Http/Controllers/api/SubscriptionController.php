<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends BaseController
{


    public function index()
    {
        $subscriptions = DB::table('subscriptions')
        ->join('subscription_validities', 'subscriptions.id', '=', 'subscription_validities.subscription_id')
        ->select(
            'subscriptions.name as name',
            'subscriptions.type as type',
            'subscriptions.image as image',
            'subscriptions.status as status',
            'subscription_validities.validity_period as validity_period', 
            'subscription_validities.price as price'
        )->get();
    
    $response = [];
    
    foreach ($subscriptions as $subscription) {
        $response[] = [
            'name' => $subscription->name,
            'type' => $subscription->type,
            'image' => $subscription->image,
            'status' => $subscription->status,
            'validity' => [
                'validity_period' => $subscription->validity_period,
                'price' => $subscription->price
            ]
        ];
    }
    
        return $this->sendResponse($response, 'Subscriptions retrieved successfully');
    }


    public function single($id)
    {
        // Fetch the subscription data
        $subscription = DB::table('subscriptions')
            ->select(
                'subscriptions.name as name',
                'subscriptions.type as type',
                'subscriptions.image as image',
                'subscriptions.status as status'
            )
            ->where('subscriptions.id', $id)
            ->first();
    
        // Initialize response
        $response = [];
    
        if ($subscription) {
            // Fetch all related subscription validity data
            $validities = DB::table('subscription_validities')
                ->select('validity_period', 'price')
                ->where('subscription_id', $id)
                ->get();
    
            // Format the response
            $response = [
                'name' => $subscription->name,
                'type' => $subscription->type,
                'image' => $subscription->image,
                'status' => $subscription->status,
                'validities' => []
            ];
    
            // Add each validity to the response
            foreach ($validities as $validity) {
                $response['validities'][] = [
                    'validity_period' => $validity->validity_period,
                    'price' => $validity->price
                ];
            }
        }
    
        return $this->sendResponse($response, 'Subscription retrieved successfully');
    }
    

    
    public function store(Request $request)
    {
      //dd($request->all());
        // Begin a transaction
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'type' => 'required|string|max:255',
                'image' => 'required',
            ]);
            if($validator->fails()){
                return $this->sendError('Validation Error.', $validator->errors());
            }
            $fields = [
                'image' => 'subscriptions/image',
            ];

            $uploadedFiles = [];

            foreach ($fields as $field => $path) {
                if ($request->hasFile($field)) {
                    $file = $request->file($field);
                    // Store the file in the specified path on S3 with its original name
                    $uploadedPath = $file->store($path, 's3');
                    // Store the file path for later use
                    $uploadedFiles[$field] = $uploadedPath;
                }
            }

            // Now you have all the URLs in the $uploadedFiles array
            $image_url = isset($uploadedFiles['image']) ? Storage::disk('s3')->url($uploadedFiles['image']) : null;
            // Insert into subscriptions table
            $subscriptionId = DB::table('subscriptions')->insertGetId([
                'name' => $request->name,
                'type' => $request->type,
                'image' => $image_url,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($request->validities as $validityData) {
                DB::table('subscription_validities')->insert([
                    'subscription_id' => $subscriptionId,
                    'validity_period' => $validityData['validity_period'],
                    'price' => $validityData['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Commit the transaction
            DB::commit();
            return $this->sendResponse('Subscription created successfully', 'Subscription created successfully');

        } catch (\Exception $e) {
            // Rollback the transaction on error
            DB::rollBack();
            return $this->sendError('Failed to create subscription', $e->getMessage());
    }
}
    public function update(Request $request, $subscriptionId)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'validities' => 'required|array',
            'validities.*.validity_period' => 'required|integer',
            'validities.*.price' => 'required|numeric',
        ]);

        // Begin a transaction
        DB::beginTransaction();

        try {
            // Update the subscription name
            DB::table('subscriptions')
                ->where('id', $subscriptionId)
                ->update([
                    'name' => $request->name,
                    'updated_at' => now(),
                ]);

            // Clear existing validities
            DB::table('subscription_validities')
                ->where('subscription_id', $subscriptionId)
                ->delete();

            // Add new validities
            foreach ($request->validities as $validity) {
                DB::table('subscription_validities')->insert([
                    'subscription_id' => $subscriptionId,
                    'validity_period' => $validity['validity_period'],
                    'price' => $validity['price'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Commit the transaction
            DB::commit();
            return $this->sendResponse('Subscription updated successfully', 'Subscription updated successfully');

        } catch (\Exception $e) {
            // Rollback the transaction on error
            DB::rollBack();
            return $this->sendError('Failed to update subscription', $e->getMessage());}
    }
}
