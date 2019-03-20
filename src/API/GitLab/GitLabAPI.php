<?php

namespace Pantheon\TerminusBuildTools\API\GitLab;

use Pantheon\TerminusBuildTools\API\WebAPI;
use Pantheon\TerminusBuildTools\API\WebAPIInterface;
use Pantheon\TerminusBuildTools\ServiceProviders\ProviderEnvironment;
use Pantheon\TerminusBuildTools\ServiceProviders\ServiceTokenStorage;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * GitLabAPI manages calls to the GitLab API.
 */
class GitLabAPI extends WebAPI
{
    const SERVICE_NAME = 'gitlab';
    const GITLAB_TOKEN = 'GITLAB_TOKEN';

    private $GITLAB_URL;

    public function serviceHumanReadableName()
    {
        return 'GitLab';
    }

    public function serviceName()
    {
        return self::SERVICE_NAME;
    }

    protected function apiClient()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => ProviderEnvironment::USER_AGENT,
        ];

        if ($this->serviceTokenStorage->hasToken(self::GITLAB_TOKEN)) {
            $headers['PRIVATE-TOKEN'] = $this->serviceTokenStorage->token(self::GITLAB_TOKEN);
        }

        return new \GuzzleHttp\Client(
            [
                'base_uri' => 'https://' . $this->getGITLABURL(),
                'headers' => $headers,
            ]
        );
    }

    public function getGITLABURL()
    {
        return $this->GITLAB_URL;
    }

    public function setGITLABURL($gitlab_url)
    {
        $this->GITLAB_URL = $gitlab_url;
    }

    protected function isPagedResponse($headers)
    {
        if (empty($headers['Link'])) {
          return FALSE;
        }
        $links = $headers['Link'];
        // Find a link header that contains a "rel" type set to "next" or "last".
        $pager_headers = array_filter($links, function ($link) {
            return strpos($link, 'rel="next"') !== FALSE || strpos($link, 'rel="last"') !== FALSE;
        });
        return !empty($pager_headers);
    }

    protected function getPagerInfo($links)
    {
        $links = $links['Link'];
        // Find a link header that contains a "rel" type set to "next" or "last".
        $pager_headers = array_filter($links, function ($link) {
            return strpos($link, 'rel="next"') !== FALSE || strpos($link, 'rel="last"') !== FALSE;
        });
        // There is only one possible link header.
        $pager_header = reset($pager_headers);
        // $pager_header looks like '<https://…>; rel="next", <https://…>; rel="last"'
        $pager_parts = array_map('trim', explode(',', $pager_header));
        $parse_link_pager_part = function ($link_pager_part) {
            // $link_pager_part is '<href>; key1="value1"; key2="value2"'
            $sub_parts = array_map('trim', explode(';', $link_pager_part));

            $href = array_shift($sub_parts);
            $href = preg_replace('@^https:\/\/' . $this->getGITLABURL() . '\/@', '', trim($href, '<>'));
            $parsed = ['href' => $href];
            return array_reduce($sub_parts, function ($carry, $sub_part) {
                list($key, $value) = explode('=', $sub_part);
                if (empty($key) || empty($value)) {
                    return $carry;
                }
                return array_merge($carry, [$key => trim($value, '"')]);
            }, $parsed);
        };
        return array_map($parse_link_pager_part, $pager_parts);
    }

    protected function isLastPage($page_link, $pager_info)
    {
        $res = array_filter($pager_info, function ($item) {
            return isset($item['rel']) && $item['rel'] === 'last';
        });
        $last_item = reset($res);

        return (isset($last_item) ? $last_item['href'] === $page_link : FALSE) || is_null($this->getNextPageUri($pager_info));
    }

    protected function getNextPageUri($pager_info)
    {
        $res = array_filter($pager_info, function ($item) {
            return isset($item['rel']) && $item['rel'] === 'next';
        });
        $next_item = reset($res);
        return isset($next_item['href']) ? $next_item['href'] : NULL;
    }
}
