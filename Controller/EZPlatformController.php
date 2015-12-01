<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) CampaignChain, Inc. <info@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Channel\EZPlatformBundle\Controller;

use CampaignChain\CoreBundle\Entity\Location;
use CampaignChain\Location\EZPlatformBundle\Entity\EZPlatformUser;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class EZPlatformController extends Controller
{
    public function newAction(Request $request)
    {
        $locationType = $this->get('campaignchain.core.form.type.location');
        $locationType->setBundleName('campaignchain/location-ezplatform');
        $locationType->setModuleIdentifier('campaignchain-ezplatform-user');
        $form = $this->createFormBuilder()
            ->add('location_name', 'text', array(
                'label' => 'Location name',
                'attr' => array('placeholder' => 'For example, "Our corporate website"'),
            ))
            ->add('url', 'url', array(
                'label' => 'REST API base URL',
                'attr' => array('placeholder' => 'For example, "http://www.example.com/api/ezp/v2/"'),
            ))
            ->add('username', 'text', array(
                'attr' => array('placeholder' => 'An eZ Platform user with access to the REST API'),
            ))
            ->add('password', 'repeated', array(
                'required'        => false,
                'type'            => 'password',
                'first_name'      => 'password',
                'second_name'     => 'password_again',
                'invalid_message' => 'The password fields must match.',
            ))
            ->getForm();

        $form->handleRequest($request);
        try {
            if ($form->isValid()) {
                $repository = $this->getDoctrine()->getManager();
                $repository->getConnection()->beginTransaction();

                $locationName   = $form->getData()['location_name'];
                $url            = $form->getData()['url'];
                $username       = $form->getData()['username'];
                $password       = $form->getData()['password'];

                /*
                 * TODO: Check whether URL exists
                 * TODO: Check that credentials are correct
                 * TODO: Check whether provided URL points to a valid eZ REST API
                 */

                $client = $this->container->get('campaignchain.channel.ezplatform.rest.client');
                $connection = $client->connect($url, $username, $password);

                $locationService = $this->get('campaignchain.core.location');
                $locationModule = $locationService->getLocationModule('campaignchain/location-ezplatform', 'campaignchain-ezplatform-user');
                $location = new Location();
                $location->setLocationModule($locationModule);
                $location->setName($locationName);
                $location->setUrl($url);

                $wizard = $this->get('campaignchain.core.channel.wizard');
                $wizard->setName($location->getName());
                $wizard->addLocation($location->getUrl(), $location);
                $channel = $wizard->persist();
                $wizard->end();

                $user = new EZPlatformUser();
                $user->setLocation($channel->getLocations()[0]);
                $user->setUsername($username);
                $user->setPassword($password);
                $repository->persist($user);
                $repository->flush();

                $repository->getConnection()->commit();

                $this->addFlash(
                    'success',
                    'The eZ Platform <a href="'.$url.'">'.$locationName.'</a> was connected successfully.'
                );
                return $this->redirect($this->generateUrl(
                    'campaignchain_core_channel'));
            }
        } catch (\Exception $e) {
            $repository->getConnection()->rollback();
            throw $e;
        }
        return $this->render(
            'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'Connect eZ Platform Instance',
                'form' => $form->createView(),
            ));
    }

}