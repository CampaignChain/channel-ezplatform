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
use Symfony\Component\HttpFoundation\Session\Session;
use Guzzle\Http\Client;
use CampaignChain\CoreBundle\Entity\Module;

class EZPlatformClient
{

    protected $baseUrl;

    protected $username;
    
    protected $password;
    
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
            $this->client = new Client($this->baseUrl);
            $this->client->setDefaultOption('auth', array(
                $this->username, $this->password
            ));
            $this->client->setDefaultOption('headers', array(
                'Accept' => 'application/vnd.ez.api.ContentInfo+json',
                ));
            return $this;
        }
        catch (ClientErrorResponseException $e) {
            $req = $e->getRequest();
            $resp =$e->getResponse();
            print_r($resp);
        }
        catch (ServerErrorResponseException $e) {

            $req = $e->getRequest();
            $resp =$e->getResponse();
            print_r($resp);
        }
        catch (BadResponseException $e) {
            $req = $e->getRequest();
            $resp =$e->getResponse();
            print_r($resp);
        }
        catch(Exception $e){
            print_r($e->getMessage());
        }
    }

    public function getContentTypes()
    {
        $request = $this->client->get('content/types');
        $response = $request->send()->json();
        return $response['ContentTypeInfoList']['ContentType'];
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

        $request = $this->client->post('views', $headers, json_encode($body));
        $response = $request->send()->json();

        return $response['View']['Result']['searchHits']['searchHit'];
    }

    public function getContentObject($id)
    {
        $request = $this->client->get('content/objects/'.$id);
        $response = $request->send()->json();
        return $response['Content'];
    }
}