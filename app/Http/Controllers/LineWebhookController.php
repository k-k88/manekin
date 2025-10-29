<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Company;
use LINE\Clients\MessagingApi\Api\MessagingApiApi;
use LINE\Clients\MessagingApi\Configuration;
use LINE\Clients\MessagingApi\Model\ReplyMessageRequest;
use LINE\Clients\MessagingApi\Model\TextMessage;
use LINE\Clients\MessagingApi\Model\FlexMessage;

class LineWebhookController extends Controller
{
    public function webhook(Request $request)
    {
        $events = $request->input('events', []);
        if (empty($events)) {
            return response('No events', 200);
        }

        $config = Configuration::getDefaultConfiguration()
            ->setAccessToken(env('LINE_CHANNEL_ACCESS_TOKEN'));
        $api = new MessagingApiApi(null, $config);

        foreach ($events as $event) {
            $replyToken = $event['replyToken'] ?? null;
            $userId = $event['source']['userId'] ?? null;
            $text = trim($event['message']['text'] ?? '');
            $replyText = null; // 初期値をnullにしておく

            // 🔹 登録コマンド（例：登録 ABC123 5）
            if (preg_match('/^登録\s+([A-Za-z0-9]+)\s+(\d+)$/u', $text, $match)) {
                $companyCode = $match[1];
                $employeeId = $match[2];
                $company = Company::where('code', $companyCode)->first();

                if (!$company) {
                    $replyText = "⚠️ 企業コード「{$companyCode}」は存在しません。";
                } else {
                    $user = User::where('id', $employeeId)
                        ->where('company_id', $company->id)
                        ->first();

                    if ($user) {
                        $user->line_user_id = $userId;
                        $user->save();
                        $replyText = "✅ {$company->name} の社員ID {$employeeId} を登録しました。";
                    } else {
                        $replyText = "⚠️ 該当する社員IDが見つかりません。";
                    }
                }
            }

            // 🔹 出勤コマンド
            elseif ($text === '出勤') {
                $user = User::where('line_user_id', $userId)->first();
                if (!$user) {
                    $replyText = "⚠️ 登録がまだです。「登録 [企業コード] [社員ID]」を送ってください。";
                } else {
                    $attendance = Attendance::firstOrCreate(
                        ['user_id' => $user->id, 'date' => now()->toDateString()],
                        ['store_id' => $user->store_id]
                    );

                    if (!$attendance->clock_in) {
                        $attendance->clock_in = now();
                        $attendance->save();
                        $replyText = "🕒 出勤を記録しました。";
                    } else {
                        $replyText = "⚠️ 今日はすでに出勤済みです。";
                    }
                }
            }

            // 🔹 退勤コマンド
            elseif ($text === '退勤') {
                $user = User::where('line_user_id', $userId)->first();
                if (!$user) {
                    $replyText = "⚠️ 登録がまだです。「登録 [企業コード] [社員ID]」を送ってください。";
                } else {
                    $attendance = Attendance::where('user_id', $user->id)
                        ->where('date', now()->toDateString())
                        ->first();

                    if ($attendance && !$attendance->clock_out) {
                        $attendance->clock_out = now();
                        $attendance->save();
                        $replyText = "🏁 退勤を記録しました。";
                    } else {
                        $replyText = "⚠️ 出勤していないか、すでに退勤済みです。";
                    }
                }
            }

            // 🔹 シフト登録コマンド
            elseif ($text === 'シフト登録') {
                $user = User::where('line_user_id', $userId)->first();

                if (!$user) {
                    $replyText = "⚠️ 登録がまだです。「登録 [企業コード] [社員ID]」を送ってください。";
                } else {
                    // FlexMessage部分だけ抜粋
$shiftUrl = url("/shift/login/{$userId}");
$api->replyMessage(new ReplyMessageRequest([
    'replyToken' => $replyToken,
    'messages' => [
        new FlexMessage([
            'type' => 'flex',
            'altText' => 'シフト登録はこちら',
            'contents' => [
                'type' => 'bubble',
                'body' => [
                    'type' => 'box',
                    'layout' => 'vertical',
                    'contents' => [
                        ['type' => 'text', 'text' => '📅 シフト登録', 'weight' => 'bold', 'size' => 'lg'],
                        ['type' => 'text', 'text' => 'カレンダーでシフトを入力できます。', 'wrap' => true, 'size' => 'sm', 'color' => '#555555'],
                        [
                            'type' => 'button',
                            'style' => 'primary',
                            'color' => '#1DB446',
                            'action' => [
                                'type' => 'uri',
                                'label' => 'シフト登録ページを開く',
                                'uri' => $shiftUrl,
                            ]
                        ]
                    ]
                ]
            ]
        ])
    ]
]));

                    continue; // ✅ このあとでTextMessageを送らない
                }
            }

            // 🔹 それ以外のメッセージ
            else {
                $replyText = "不明なコマンドです。\n\n🟢利用できるコマンド\n・登録 [企業コード] [社員ID]\n・出勤\n・退勤\n・シフト登録";
            }

            // 🔹 通常メッセージを返信
            if ($replyToken && $replyText) {
                $api->replyMessage(new ReplyMessageRequest([
                    'replyToken' => $replyToken,
                    'messages' => [new TextMessage([
                        'type' => 'text',
                        'text' => $replyText,
                    ])],
                ]));
            }
        }

        return response('OK', 200);
    }
}
