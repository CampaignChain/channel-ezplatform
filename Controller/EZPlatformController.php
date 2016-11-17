<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
                $em = $this->getDoctrine()->getManager();
                $em->getConnection()->beginTransaction();

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
                $em->persist($user);
                $em->flush();

                $em->getConnection()->commit();

                $this->addFlash(
                    'success',
                    'The eZ Platform <a href="'.$url.'">'.$locationName.'</a> was connected successfully.'
                );
                return $this->redirect($this->generateUrl(
                    'campaignchain_core_location'));
            }
        } catch (\Exception $e) {
            $em->getConnection()->rollback();
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