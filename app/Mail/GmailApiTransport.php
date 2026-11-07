<?php

namespace App\Mail;

use Google\Client;
use Google\Service\Gmail;
use Google\Service\Gmail\Message as GmailMessage;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

class GmailApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $refreshToken,
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $client = new Client();
        $client->setClientId($this->clientId);
        $client->setClientSecret($this->clientSecret);
        $client->setScopes([Gmail::GMAIL_SEND]);

        $token = $client->fetchAccessTokenWithRefreshToken(
            $this->refreshToken
        );

        if (!is_array($token) || empty($token['access_token'])) {
            throw new TransportException(
                'Gmail authorization failed. Check your OAuth credentials.'
            );
        }

        $client->setAccessToken($token);

        $mime = $message->getOriginalMessage()->toString();

        $encoded = rtrim(
            strtr(base64_encode($mime), '+/', '-_'),
            '='
        );

        $gmailMessage = new GmailMessage();
        $gmailMessage->setRaw($encoded);

        $gmail = new Gmail($client);
        $gmail->users_messages->send('me', $gmailMessage);
    }

    public function __toString(): string
    {
        return 'gmail_api';
    }
}
