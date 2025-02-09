<?php

namespace LaravelFCM\Sender;

use LaravelFCM\Message\Topics;
use LaravelFCM\Request\Request;
use LaravelFCM\Message\Options;
use LaravelFCM\Message\PayloadData;
use LaravelFCM\Response\GroupResponse;
use LaravelFCM\Response\TopicResponse;
use GuzzleHttp\Exception\ClientException;
use LaravelFCM\Response\DownstreamResponse;
use LaravelFCM\Message\PayloadNotification;

class FCMSender extends HTTPSender
{
    const MAX_TOKEN_PER_REQUEST = 1000;

    /**
     * send a downstream message to.
     *
     * - a unique device with is registration Token
     * - or to multiples devices with an array of registrationIds
     *
     * @param string|array             $to
     * @param Options|null             $options
     * @param PayloadNotification|null $notification
     * @param PayloadData|null         $data
     * @param string                   $configKey
     *
     * @return DownstreamResponse|null
     */
    public function sendTo($to, Options $options = null, PayloadNotification $notification = null, PayloadData $data = null, $configKey = null)
    {
        $response = null;

        if (is_array($to) && !empty($to)) {
            $partialTokens = array_chunk($to, self::MAX_TOKEN_PER_REQUEST, false);
            foreach ($partialTokens as $tokens) {
                $request = new Request($tokens, $options, $notification, $data, null, $configKey);

                $responseGuzzle = $this->post($request);

                $responsePartial = new DownstreamResponse($responseGuzzle, $tokens, $this->logger);
                if (!$response) {
                    $response = $responsePartial;
                } else {
                    $response->merge($responsePartial);
                }
            }
        } else {
            $request = new Request($to, $options, $notification, $data, null, $configKey);
            $responseGuzzle = $this->post($request);

            $response = new DownstreamResponse($responseGuzzle, $to, $this->logger);
        }

        return $response;
    }

    /**
     * Send a message to a group of devices identified with them notification key.
     *
     * @param string|string[]          $notificationKey
     * @param Options|null             $options
     * @param PayloadNotification|null $notification
     * @param PayloadData|null         $data
     *
     * @return GroupResponse
     */
    public function sendToGroup($notificationKey, Options $options = null, PayloadNotification $notification = null, PayloadData $data = null, $configKey)
    {
        $request = new Request($notificationKey, $options, $notification, $data, null, $configKey);

        $responseGuzzle = $this->post($request);

        return new GroupResponse($responseGuzzle, $notificationKey, $this->logger);
    }

    /**
     * Send message devices registered at a or more topics.
     *
     * @param Topics                   $topics
     * @param Options|null             $options
     * @param PayloadNotification|null $notification
     * @param PayloadData|null         $data
     *
     * @return TopicResponse
     */
    public function sendToTopic(Topics $topics, Options $options = null, PayloadNotification $notification = null, PayloadData $data = null, $configKey)
    {
        $request = new Request(null, $options, $notification, $data, $topics, $configKey);

        $responseGuzzle = $this->post($request);

        return new TopicResponse($responseGuzzle, $topics, $this->logger);
    }

    /**
     * @internal
     *
     * @param \LaravelFCM\Request\Request $request
     *
     * @return null|\Psr\Http\Message\ResponseInterface
     */
    protected function post($request)
    {
        try {
            $responseGuzzle = $this->client->request('post', $this->url, $request->build());
        } catch (ClientException $e) {
            $responseGuzzle = $e->getResponse();
        }

        return $responseGuzzle;
    }
}
