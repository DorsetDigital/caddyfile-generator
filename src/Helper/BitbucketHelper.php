<?php

namespace DorsetDigital\Caddy\Helper;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\Security\Security;

class BitbucketHelper
{
    use Injectable;
    use Configurable;

    const REQUIRED_ENV = [
        'BITBUCKET_ACCESS_TOKEN',
        'BITBUCKET_REPO_OWNER',
        'BITBUCKET_REPO_SLUG',
        'BITBUCKET_BRANCH_NAME',
        'BITBUCKET_PRODUCTION_BRANCH_NAME'
    ];

    const BB_STATUS_OK = 0;
    const BB_STATUS_ERROR = 1;

    private static $bitbucket_base_uri = 'https://api.bitbucket.org/2.0/repositories/';
    private static $bitbucket_source_slug = 'src';
    private static $bitbucket_pr_slug = 'pullrequests';
    private static $pr_title = 'Merge changes from main to release/Production';
    private static $pr_description = 'Deploy latest config';

    private $responseMessage;
    private $status;


    public function __construct()
    {
        foreach (self::REQUIRED_ENV as $env) {
            if (!Environment::getEnv($env)) {
                return false;
            }
        }
        return $this;
    }


    public function commitFile($fileContent, $repoPath, $commitMessage = null)
    {

        $client = new Client();
        $message = $commitMessage ?? $this->getDefaultCommitMessage();
        $body = [
            'message' => $message,
            'branch' => $this->getBranchName(),
            $repoPath => $fileContent
        ];

        try {
            $response = $client->post($this->getCommitURL(), [
                'headers' => $this->getCommitRequestHeaders(),
                'form_params' => $body,
            ]);

            // Check if the request was successful (HTTP status code 201)
            if ($response->getStatusCode() == 201) {
                $this->setMessage(_t(__CLASS__ . 'CommitSuccess', 'Files committed successfully.'));
                $this->setStatus(self::BB_STATUS_OK);
            } else {
                $this->setMessage(_t(__CLASS__ . 'CommitError', 'Error committimng files: {message}', [
                    'message' => $response->getStatusCode()
                ]));
                $this->setStatus(self::BB_STATUS_ERROR);
            }
        } catch (Exception $e) {
            $this->setMessage(_t(__CLASS__ . 'CommitError', 'Error committimng files: {message}', [
                'message' => $e->getMessage()
            ]));
            $this->setStatus(self::BB_STATUS_ERROR);
        }

        return $this;
    }


    public function createPR()
    {
        $prTitle = $this->config()->get('pr_title');
        $prDescription = $this->config()->get('pr_description');

        $client = new Client();

        $body = [
            'title' => $prTitle,
            'description' => $prDescription,
            'source' => [
                'branch' => [
                    'name' => $this->getBranchName()
                ]
            ],
            'destination' => [
                'branch' => [
                    'name' => $this->getProductionBranchName()
                ]
            ],
            'close_source_branch' => false,
        ];

        echo "<pre>";
        print_r($body);
        echo "</pre>";


        try {
            $response = $client->post($this->getPRURL(), [
                'headers' => $this->getPRRequestHeaders(),
                'json' => $body,
            ]);

            if ($response->getStatusCode() == 201) {
                $this->setMessage(_t(__CLASS__ . 'PRSuccess', 'Pull Request created successfully.'));
                $this->setStatus(self::BB_STATUS_OK);
            } else {
                $this->setMessage(_t(__CLASS__ . 'PRError', 'Error creating PR: {message}', [
                    'message' => $response->getStatusCode()
                ]));
                $this->setStatus(self::BB_STATUS_ERROR);
            }
        } catch (GuzzleException $e) {
            $this->setMessage(_t(__CLASS__ . 'PRError', 'Error creating PR: {message}', [
                'message' => $e->getMessage()
            ]));
            $this->setStatus(self::BB_STATUS_ERROR);
        }

        return $this;
    }


    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->responseMessage;
    }

    /**
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param $message
     * @return void
     */
    private function setMessage($message)
    {
        $this->responseMessage = $message;
    }

    /**
     * @param $status
     * @return void
     */
    private function setStatus($status)
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    private function getBranchName()
    {
        return Environment::getEnv('BITBUCKET_BRANCH_NAME');
    }

    private function getProductionBranchName()
    {
        return Environment::getEnv('BITBUCKET_PRODUCTION_BRANCH_NAME');
    }

    /**
     * @return string
     */
    private function getCommitURL()
    {
        $base = $this->config()->get('bitbucket_base_uri');
        $slug = $this->config()->get('bitbucket_source_slug');

        return Controller::join_links([
            $base,
            Environment::getEnv('BITBUCKET_REPO_OWNER'),
            Environment::getEnv('BITBUCKET_REPO_SLUG'),
            $slug
        ]);
    }

    private function getPRURL()
    {
        $base = $this->config()->get('bitbucket_base_uri');
        $slug = $this->config()->get('bitbucket_pr_slug');

        return Controller::join_links([
            $base,
            Environment::getEnv('BITBUCKET_REPO_OWNER'),
            Environment::getEnv('BITBUCKET_REPO_SLUG'),
            $slug
        ]);
    }

    /**
     * @return mixed
     */
    private function getAccessToken()
    {
        return Environment::getEnv('BITBUCKET_ACCESS_TOKEN');
    }

    /**
     * @return array
     */
    private function getCommitRequestHeaders()
    {
        return [
            'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];
    }

    private function getPRRequestHeaders()
    {
        return [
            'Authorization' => sprintf('Bearer %s', $this->getAccessToken()),
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * @return string
     */
    private function getDefaultCommitMessage()
    {
        return _t(__CLASS__ . '.DefaultCommit', '({user}) Config update - {timestamp}', [
            'user' => Security::getCurrentUser()->getName(),
            'timestamp' => DBDatetime::now()->Format(DBDatetime::ISO_DATETIME)
        ]);
    }
}
