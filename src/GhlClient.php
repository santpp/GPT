<?php
declare(strict_types=1);
// Server-only adapter. No arbitrary URL proxy and no automatic retry of writes.
final class GhlClient {
    public function __construct(private readonly string $token) {}
    public function checkLocation(string $id): void {
        $result=$this->get('/locations/'.rawurlencode($id),'2021-07-28');
        if(($result['location']['id'] ?? null)!==$id) throw new RuntimeException('Location mismatch');
    }
    public function conversationMessages(string $id): array {
        return $this->get('/conversations/'.rawurlencode($id).'/messages', '2021-04-15');
    }
    public function contact(string $id): array {
        return $this->get('/contacts/'.rawurlencode($id), '2021-07-28');
    }
    private function get(string $path, string $version): array {
        if ($this->token === '') throw new RuntimeException('GHL credential missing');
        $ch = curl_init('https://services.leadconnectorhq.com'.$path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>15, CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$this->token,'Version: '.$version,'Accept: application/json']]);
        $raw = curl_exec($ch);
        $status = curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($raw === false || $status < 200 || $status >= 300) throw new RuntimeException('GHL request failed: '.$status);
        return json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    }
}
