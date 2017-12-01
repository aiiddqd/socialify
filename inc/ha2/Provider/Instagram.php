<?php
/*!
* Hybridauth
* https://hybridauth.github.io | https://github.com/hybridauth/hybridauth
*  (c) 2017 Hybridauth authors | https://hybridauth.github.io/license.html
*/

namespace Hybridauth\Provider;

use Hybridauth\Adapter\OAuth2;
use Hybridauth\Exception\UnexpectedApiResponseException;
use Hybridauth\Data;
use Hybridauth\User;

/**
 * Instagram OAuth2 provider adapter.
 */
class Instagram extends OAuth2
{
    /**
    * {@inheritdoc}
    */
    protected $scope = 'basic';

    /**
    * {@inheritdoc}
    */
    protected $apiBaseUrl = 'https://api.instagram.com/v1/';

    /**
    * {@inheritdoc}
    */
    protected $authorizeUrl = 'https://api.instagram.com/oauth/authorize/';

    /**
    * {@inheritdoc}
    */
    protected $accessTokenUrl = 'https://api.instagram.com/oauth/access_token';

    /**
    * {@inheritdoc}
    */
    protected $apiDocumentation = 'https://www.instagram.com/developer/authentication/';

    /**
    * {@inheritdoc}
    */
    public function getUserProfile()
    {
        $response = $this->apiRequest('users/self/');

        $data = new Data\Collection($response);

        if (! $data->exists('data')) {
            throw new UnexpectedApiResponseException('Provider API returned an unexpected response.');
        }

        $userProfile = new User\Profile();

        $data = $data->filter('data');

        $userProfile->identifier  = $data->get('id');
        $userProfile->description = $data->get('bio');
        $userProfile->photoURL    = $data->get('profile_picture');
        $userProfile->webSiteURL  = $data->get('website');
        $userProfile->displayName = $data->get('full_name');

        $userProfile->displayName = $userProfile->displayName ?: $data->get('username');

        return $userProfile;
    }
}
