<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LineWebhookController;

Route::post('/line/webhook', [LineWebhookController::class, 'webhook']);


?>
