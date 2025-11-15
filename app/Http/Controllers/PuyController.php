<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use App\Models\Book;

class PaymentController extends Controller
{
    public function initiatePurchase(Request $request, $bookId)
    {
        $user = $request->user();
        $book = Book::find($bookId);
        if (!$book) return response()->json(['message'=>'الكتاب غير موجود'], 404);

        if ($book->book_type === 'free') {
            return response()->json(['message'=>'هذا الكتاب مجاني'], 400);
        }

        $amountCents = intval($book->price) * 100; // تأكدي من عملة السعر

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'customer_email' => $user->email,
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $book->title,
                        'description' => substr($book->description ?? '', 0, 200),
                    ],
                    'unit_amount' => $amountCents,
                ],
                'quantity' => 1,
            ]],
            'success_url' => config('app.url') . '/payment-success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.url') . '/payment-cancel',
            'metadata' => [
                'user_id' => $user->id,
                'book_id' => $book->id,
            ],
        ]);

        return response()->json([
            'checkout_url' => $session->url,
            'session_id' => $session->id
        ]);
    }
}
