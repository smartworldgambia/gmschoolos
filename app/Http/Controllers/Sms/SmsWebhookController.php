<?php

namespace App\Http\Controllers\Sms;

use App\Http\Controllers\Controller;
use App\Services\Sms\SmsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Delivery reports from SMS providers. Public and stateless; authenticity is checked before anything is touched:
 *  - Twilio signs every request (X-Twilio-Signature, HMAC-SHA1 over URL + sorted POST fields with the account token).
 *  - Africa's Talking does not sign, so the callback URL carries a long random secret (AT_WEBHOOK_SECRET).
 * A report can only move a message forward: delivered is final and reports for unknown references are ignored.
 */
class SmsWebhookController extends Controller
{
    public function twilio(Request $request, SmsService $sms): Response
    {
        $token = (string) config('schoolos.sms.twilio.token');
        if ($token === '' || ! $this->validTwilioSignature($request, $token)) {
            return response('forbidden', 403);
        }

        $status = match (strtolower((string) $request->input('MessageStatus'))) {
            'delivered' => 'delivered',
            'sent' => 'sent',
            'undelivered', 'failed' => 'failed',
            default => null,
        };

        if ($status) {
            $code = $request->input('ErrorCode');
            $sms->applyReport('twilio', (string) $request->input('MessageSid'), $status, $code ? 'twilio_error_'.$code : null);
        }

        return response('', 204);
    }

    public function africastalking(Request $request, string $secret, SmsService $sms): Response
    {
        $expected = (string) config('schoolos.sms.africastalking.webhook_secret');
        if ($expected === '' || ! hash_equals($expected, $secret)) {
            return response('forbidden', 403);
        }

        $status = match (strtolower((string) $request->input('status'))) {
            'success', 'delivered' => 'delivered',
            'sent' => 'sent',
            'failed', 'rejected', 'expired', 'undelivered' => 'failed',
            default => null,
        };

        if ($status) {
            $sms->applyReport('africastalking', (string) $request->input('id'), $status, $request->input('failureReason') ? mb_substr((string) $request->input('failureReason'), 0, 120) : null);
        }

        return response('', 204);
    }

    private function validTwilioSignature(Request $request, string $token): bool
    {
        $given = (string) $request->header('X-Twilio-Signature');
        if ($given === '') {
            return false;
        }

        $url = (string) (config('schoolos.sms.twilio.status_callback') ?: $request->fullUrl());
        $params = $request->post();
        ksort($params);

        $data = $url;
        foreach ($params as $k => $v) {
            $data .= $k.(is_array($v) ? implode('', $v) : $v);
        }

        return hash_equals(base64_encode(hash_hmac('sha1', $data, $token, true)), $given);
    }
}
