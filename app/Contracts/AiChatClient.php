<?php

namespace App\Contracts;

interface AiChatClient
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, bool $jsonObject = false): string;
}
