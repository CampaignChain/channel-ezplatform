<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Channel\EZPlatformBundle\REST;

use CampaignChain\CoreBundle\Entity\Activity;
use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\CoreBundle\Exception\ExternalApiException;
use GuzzleHttp\Client;

class EZPlatformClient
{

    protected $baseUrl;

    protected $username;
    
    protected $password;

    /** @var  Client */
    protected $client;
    
    public function setContainer($container)
    {
        $this->container = $container;
    }

    public function connectByActivity(Activity $activity){
        return $this->connectByLocation($activity->getLocation());
    }
    
    public function connectByLocation(Location $location){
        $ezUser = $this->container->get('doctrine')->getRepository('CampaignChainLocationEZPlatformBundle:EZPlatformUser')->findOneByLocation($location);
        return $this->connect($location->getUrl(), $ezUser->getUsername(), $ezUser->getPassword());
    }

    public function connect($baseUrl, $username, $password){
        // Append trailing slash if missing.
        if (substr($baseUrl, -1) !== '/') {
            return $baseUrl.'/';
        }

        $this->baseUrl  = $baseUrl;
        $this->username = $username;        
        $this->password = $password;

        try {
            $this->client = new Client([
                'base_uri' => self::BASE_URL,
                'headers' => array(
                    'Accept' => 'application/vnd.ez.api.ContentInfo+json',
                ),
                'auth' => array(
                    $this->username, $this->password
                ),
            ]);

            return $this;
        } catch (\Exception $e) {
            throw new ExternalApiException($e->getMessage(), $e->getCode());
        }
    }

    private function request($method, $uri, $body = array())
    {
        try {
            $res = $this->client->request($method, $uri, $body);
            return json_decode($res->getBody(), true);
        } catch(\Exception $e){
            throw new ExternalApiException($e->getMessage(), $e->getCode());
        }
    }

    public function getContentTypes()
    {
        $res = $this->request('GET', 'content/types');
        return $res['ContentTypeInfoList']['ContentType'];
    }

    public function getUnpublishedContentObjectsByContentTypeId($id)
    {
        // Get the criteria for unpublished content.
        $moduleService = $this->container->get('campaignchain.core.module');
        $module = $moduleService->getModule(
            'campaignchain/channel-ezplatform',
            'campaignchain-ezplatform'
        );
        $criteria = $module->getParams()['ez_unpublished_criteria'];
        $criteria['ContentTypeIdCriterion'] = $id;

        $headers = array(
            'Accept'        => 'application/vnd.ez.api.View+json',
            'Content-Type'  => 'application/vnd.ez.api.ViewInput+json'
        );
        $body = array(
            'ViewInput' => array(
                'identifier' => 'content-by-type',
                'Query' => array(
                    'Criteria' => $criteria,
                ),
            )
        );

        $res = $this->request('POST','views', array(
                'headers' => $headers,
                'body' => json_encode($body),
            )
        );

        return $res['View']['Result']['searchHits']['searchHit'];
    }

    public function getContentObject($id)
    {
        $res = $this->request('GET','content/objects/'.$id);
        return $res['Content'];
    }
}