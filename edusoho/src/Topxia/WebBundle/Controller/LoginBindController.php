<?php

namespace Topxia\WebBundle\Controller;

use Topxia\Common\SimpleValidator;
use Symfony\Component\HttpFoundation\Request;
use Topxia\Component\OAuthClient\OAuthClientFactory;

class LoginBindController extends BaseController
{
    public function indexAction(Request $request, $type)
    {
        if ($request->query->has('_target_path')) {
            $targetPath = $request->query->get('_target_path');

            if ($targetPath == '') {
                $targetPath = $this->generateUrl('homepage');
            }

            if (!in_array($targetPath, $this->getBlacklist())) {
                $request->getSession()->set('_target_path', $targetPath);
            }
        }

        $inviteCode  = $request->query->get('inviteCode', null);
        $client      = $this->createOAuthClient($type);

        $token = $this->getTokenService()->makeToken('login.bind', array(
            'data'     => array(
                'type' => $type,
                'sessionId' => $request->getSession()->getId(),
            ),
            'times'    => 1,
            'duration' => 3600
        ));

        $callbackUrl = $this->generateUrl('login_bind_callback', array('type' => $type, 'token' => $token['token']), true);

        if ($inviteCode) {
            $callbackUrl = $callbackUrl.'&inviteCode='.$inviteCode;
        }

        $url = $client->getAuthorizeUrl($callbackUrl);

        return $this->redirect($url);
    }

    protected function getBlacklist()
    {
        return array('/partner/logout');
    }

    public function callbackAction(Request $request, $type)
    {
        $code        = $request->query->get('code');
        $inviteCode  = $request->query->get('inviteCode');
        $token  = $request->query->get('token', '');

        $this->validateToken($request, $type);

        $callbackUrl = $this->generateUrl('login_bind_callback', array('type' => $type, 'token' => $token), true);
        $token       = $this->createOAuthClient($type)->getAccessToken($code, $callbackUrl);

        $bind        = $this->getUserService()->getUserBindByTypeAndFromId($type, $token['userId']);

        $request->getSession()->set('oauth_token', $token);

        if ($bind) {
            $user = $this->getUserService()->getUser($bind['toId']);

            if (empty($user)) {
                $this->setFlashMessage('danger', $this->trans('?????????????????????????????????????????????'));

                return $this->redirect($this->generateUrl('register'));
            }

            $this->authenticateUser($user);

            if ($this->getAuthService()->hasPartnerAuth()) {
                return $this->redirect($this->generateUrl('partner_login', array('goto' => $this->getTargetPath($request))));
            } else {
                $goto = $this->getTargetPath($request);

                return $this->redirect($goto);
            }
        } else {
            return $this->redirect($this->generateUrl('login_bind_choose', array('type' => $type, 'inviteCode' => $inviteCode)));
        }
    }

    protected function validateToken($request, $type)
    {
        $token        = $request->query->get('token', '');
        if (empty($token)) {
            throw $this->createAccessDeniedException();
        }

        $token    = $this->getTokenService()->verifyToken('login.bind', $token);
        $tokenData = $token['data'];
        if ($tokenData['type'] != $type) {
            throw $this->createAccessDeniedException();
        }

        if ($tokenData['sessionId'] != $request->getSession()->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    public function chooseAction(Request $request, $type)
    {
        $token      = $request->getSession()->get('oauth_token');
        $inviteCode = $request->query->get('inviteCode', '');
        $inviteUser = $inviteCode ? $inviteUser = $this->getUserService()->getUserByInviteCode($inviteCode) : array();

        $client      = $this->createOAuthClient($type);
        $clientMetas = OAuthClientFactory::clients();
        $clientMeta  = $clientMetas[$type];

        try {
            $oauthUser         = $client->getUserInfo($token);
            $oauthUser['name'] = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-z0-9_.]+/u', '', $oauthUser['name']);
            $oauthUser['name'] = str_replace(array('-'), array('_'), $oauthUser['name']);
        } catch (\Exception $e) {
            $message = $e->getMessage();

            if ($message == 'unaudited') {
                $message = $this->trans('????????????????????????????????????????????????????????????%name%????????????????????????????????????', array('%name%' => $clientMeta['name']));
            } elseif($message == 'unAuthorize') {
                return $this->redirect($this->generateUrl('login'));
            } else {
                $message = $this->trans('????????????????????????????????????????????????????????????%message%', array('%message%' => $message));
            }

            $this->setFlashMessage('danger', $message);

            return $this->redirect($this->generateUrl('login'));
        }

        $name = $this->mateName($type);
        return $this->render('TopxiaWebBundle:Login:bind-choose.html.twig', array(
            'inviteUser'     => $inviteUser,
            'oauthUser'      => $oauthUser,
            'type'           => $type,
            'name'           => $name,
            'hasPartnerAuth' => $this->getAuthService()->hasPartnerAuth()
        ));
    }

    public function newAction(Request $request, $type)
    {
        return $this->createJsonResponse($this->autobind($request, $type));
    }

    private function autobind(Request $request, $type)
    {
        $token = $request->getSession()->get('oauth_token');

        if (empty($token)) {
            $response = array('success' => false, 'message' => $this->trans('????????????????????????????????????'));
            goto response;
        }

        $client                 = $this->createOAuthClient($type);
        $oauthUser              = $client->getUserInfo($token);
        $oauthUser['createdIp'] = $request->getClientIp();

        if (empty($oauthUser['id'])) {
            $response = array('success' => false, 'message' => $this->trans('??????????????????????????????????????????????????????'));
            goto response;
        }

        if (!$this->getAuthService()->isRegisterEnabled()) {
            $response = array('success' => false, 'message' => $this->trans('?????????????????????????????????????????????'));
            goto response;
        }

        $user = $this->generateUser($type, $token, $oauthUser, $setData = array());

        if (empty($user)) {
            $response = array('success' => false, 'message' => $this->trans('???????????????????????????'));
            goto response;
        }

        if (!empty($oauthUser['name']) && !empty($oauthUser['email'])) {
            $this->getUserService()->setupAccount($user['id']);
        }

        $this->authenticateUser($user);
        $response = array('success' => true, '_target_path' => $this->getTargetPath($request));

        response:
        return $response;
    }

    public function weixinIndexAction(Request $request)
    {
        return $this->render('TopxiaWebBundle:Login:bind-weixin.html.twig', array(
            'hasPartnerAuth' => $this->getAuthService()->hasPartnerAuth()
        ));
    }

    public function newSetAction(Request $request, $type)
    {
        $setData = $request->request->all();

        if (isset($setData['set_bind_emailOrMobile']) && !empty($setData['set_bind_emailOrMobile'])) {
            if (SimpleValidator::email($setData['set_bind_emailOrMobile'])) {
                $setData['email'] = $setData['set_bind_emailOrMobile'];
            } elseif (SimpleValidator::mobile($setData['set_bind_emailOrMobile'])) {
                $setData['mobile'] = $setData['set_bind_emailOrMobile'];
            }

            unset($setData['set_bind_emailOrMobile']);
        }

        $token = $request->getSession()->get('oauth_token');

        if (empty($token)) {
            $response = array('success' => false, 'message' => $this->trans('????????????????????????????????????'));
            goto response;
        }

        $client                 = $this->createOAuthClient($type);
        $oauthUser              = $client->getUserInfo($token);
        $oauthUser['createdIp'] = $request->getClientIp();

        if (empty($oauthUser['id'])) {
            $response = array('success' => false, 'message' => $this->trans('??????????????????????????????????????????????????????'));
            goto response;
        }

        if (!$this->getAuthService()->isRegisterEnabled()) {
            $response = array('success' => false, 'message' => $this->trans('?????????????????????????????????????????????'));
            goto response;
        }

        $user = $this->generateUser($type, $token, $oauthUser, $setData);

        if (empty($user)) {
            $response = array('success' => false, 'message' => $this->trans('???????????????????????????'));
            goto response;
        }

        if (!$user['setup'] && isset($setData['email']) && stripos($setData['email'], '@edusoho.net') != false) {
            $this->getUserService()->setupAccount($user['id']);
        }

        $this->authenticateUser($user);

        if (!empty($oauthUser['avatar'])) {
            $this->getUserService()->changeAvatarFromImgUrl($user['id'], $oauthUser['avatar']);
        }

        $redirectUrl = $this->generateUrl('register_success', array(
            'goto' => $this->getTargetPath($request)
        ));
        $response = array('success' => true, '_target_path' => $redirectUrl);

        response:
        return $this->createJsonResponse($response);
    }

    protected function generateUser($type, $token, $oauthUser, $setData)
    {
        $registration      = array();
        $randString        = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
        $oauthUser['name'] = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-z0-9_.]+/u', '', $oauthUser['name']);
        $oauthUser['name'] = str_replace(array('-'), array('_'), $oauthUser['name']);

        if (!SimpleValidator::nickname($oauthUser['name'])) {
            $oauthUser['name'] = '';
        }

        $tempType = $type;

        if (empty($oauthUser['name'])) {
            if ($type == 'weixinmob' || $type == 'weixinweb') {
                $tempType = 'weixin';
            }

            $oauthUser['name'] = $tempType.substr($randString, 9, 3);
        }

        $nameLength = mb_strlen($oauthUser['name'], 'utf-8');

        if ($nameLength > 10) {
            $oauthUser['name'] = mb_substr($oauthUser['name'], 0, 11, 'utf-8');
        }

        if (!empty($setData['nickname']) && !empty($setData['email'])) {
            $registration['nickname']      = $setData['nickname'];
            $registration['email']         = $setData['email'];
            $registration['emailOrMobile'] = $setData['email'];
        } else {
            $nicknames   = array();
            $nicknames[] = isset($setData['nickname']) ? $setData['nickname'] : $oauthUser['name'];
            $nicknames[] = mb_substr($oauthUser['name'], 0, 8, 'utf-8').substr($randString, 0, 3);
            $nicknames[] = mb_substr($oauthUser['name'], 0, 8, 'utf-8').substr($randString, 3, 3);
            $nicknames[] = mb_substr($oauthUser['name'], 0, 8, 'utf-8').substr($randString, 6, 3);

            foreach ($nicknames as $name) {
                if ($this->getUserService()->isNicknameAvaliable($name)) {
                    $registration['nickname'] = $name;
                    break;
                }
            }

            if (empty($registration['nickname'])) {
                return;
            }

            $registration['email'] = 'u_'.substr($randString, 0, 12).'@edusoho.net';
        }

        if ($this->getSensitiveService()->scanText($registration['nickname'])) {
            return $this->createMessageResponse('error', $this->trans('??????????????????????????????'));
        }

        $registration['password']  = substr(base_convert(sha1(uniqid(mt_rand(), true)), 16, 36), 0, 8);
        $registration['token']     = $token;
        $registration['createdIp'] = $oauthUser['createdIp'];

        if (isset($setData['mobile']) && !empty($setData['mobile'])) {
            $registration['mobile']        = $setData['mobile'];
            $registration['emailOrMobile'] = $setData['mobile'];
        }

        if (isset($setData['invite_code']) && !empty($setData['invite_code'])) {
            $registration['invite_code'] = $setData['invite_code'];
        }

        $user = $this->getAuthService()->register($registration, $type);

        return $user;
    }

    public function existAction(Request $request, $type)
    {
        $token     = $request->getSession()->get('oauth_token');
        $client    = $this->createOAuthClient($type);
        $oauthUser = $client->getUserInfo($token);
        $data      = $request->request->all();

        $message = $this->trans('Email?????????????????????????????????');

        if (SimpleValidator::email($data['emailOrMobile'])) {
            $user    = $this->getUserService()->getUserByEmail($data['emailOrMobile']);
            $message = $this->trans('???Email??????????????????');
        } elseif (SimpleValidator::mobile($data['emailOrMobile'])) {
            $user    = $this->getUserService()->getUserByVerifiedMobile($data['emailOrMobile']);
            $message = $this->trans('???????????????????????????');
        }

        if (empty($user)) {
            $response = array('success' => false, 'message' => $message);
        } elseif (!$this->getUserService()->verifyPassword($user['id'], $data['password'])) {
            $response = array('success' => false, 'message' => $this->trans('??????????????????????????????'));
        } elseif ($this->getUserService()->getUserBindByTypeAndUserId($type, $user['id'])) {
            $response = array('success' => false, 'message' => $this->trans('???%siteName%?????????????????????????????????????????????????????????????????????????????????????????????????????????????????????', array('%siteName%' => $this->setting('site.name'))));
        } else {
            $response = array('success' => true, '_target_path' => $this->getTargetPath($request));
            $this->getUserService()->bindUser($type, $oauthUser['id'], $user['id'], $token);
            $this->authenticateUser($user);
        }

        return $this->createJsonResponse($response);
    }

    public function existBindAction(Request $request)
    {
        $token     = $request->getSession()->get('oauth_token');
        $type      = 'weixinmob';
        $client    = $this->createOAuthClient($type);
        $oauthUser = $client->getUserInfo($token);
        $olduser   = $this->getCurrentUser();
        $userBinds = $this->getUserService()->unBindUserByTypeAndToId($type, $olduser->id);
        $data      = $request->request->all();

        $message = $this->trans('Email?????????????????????????????????');

        if (SimpleValidator::email($data['emailOrMobile'])) {
            $user    = $this->getUserService()->getUserByEmail($data['emailOrMobile']);
            $message = $this->trans('???Email??????????????????');
        } elseif (SimpleValidator::mobile($data['emailOrMobile'])) {
            $user    = $this->getUserService()->getUserByVerifiedMobile($data['emailOrMobile']);
            $message = $this->trans('????????????????????????????????????');
        }

        if (empty($user)) {
            $response = array('success' => false, 'message' => $message);
        } elseif (!$this->getUserService()->verifyPassword($user['id'], $data['password'])) {
            $response = array('success' => false, 'message' => $this->trans('??????????????????????????????'));
        } elseif ($this->getUserService()->getUserBindByTypeAndUserId($type, $user['id'])) {
            $response = array('success' => false, 'message' => $this->trans('????????????????????????????????????????????????????????????????????????????????????????????????????????????????????????'));
        } else {
            $response = array('success' => true, '_target_path' => $this->getTargetPath($request));
            $this->getUserService()->bindUser($type, $oauthUser['id'], $user['id'], $token);
            $this->authenticateUser($user);
        }

        return $this->createJsonResponse($response);
    }

    public function changeToExistAction(Request $request, $type)
    {
        $token = $request->getSession()->get('oauth_token');

        if (empty($token)) {
            return $this->createMessageResponse('error', $this->trans('????????????????????????????????????'));
        }

        $client    = $this->createOAuthClient($type);
        $oauthUser = $client->getUserInfo($token);
        $name      = $this->mateExistName($type);
        return $this->render('TopxiaWebBundle:Login:bind-choose-exist.html.twig', array(
            'oauthUser'      => $oauthUser,
            'type'           => $type,
            'name'           => $name,
            'hasPartnerAuth' => $this->getAuthService()->hasPartnerAuth()
        ));
    }

    protected function mateName($type)
    {
        switch ($type) {
            case 'weixinweb':
            case 'weixinmob':
                return $this->trans('??????????????????');
                break;
            case 'weibo':
                return $this->trans('??????????????????');
                break;
            case 'qq':
                return $this->trans('QQ????????????');
                break;
            case 'renren':
                return $this->trans('??????????????????');
                break;
            default:
                return '';
        }
    }

    protected function mateExistName($type)
    {
        switch ($type) {
            case 'weixinweb':
            case 'weixinmob':
                return $this->trans('????????????????????????');
                break;
            case 'weibo':
                return $this->trans('????????????????????????');
                break;
            case 'qq':
                return $this->trans('QQ??????????????????');
                break;
            case 'renren':
                return $this->trans('????????????????????????');
                break;
            default:
                return '';
        }
    }

    protected function createOAuthClient($type)
    {
        $settings = $this->setting('login_bind');

        if (empty($settings)) {
            throw new \RuntimeException($this->trans('?????????????????????????????????????????????????????????'));
        }

        if (empty($settings) || !isset($settings[$type.'_enabled']) || empty($settings[$type.'_key']) || empty($settings[$type.'_secret'])) {
            throw new \RuntimeException($this->trans('???????????????(%type%)??????????????????????????????????????????', array('%type%' => $type)));
        }

        if (!$settings[$type.'_enabled']) {
            throw new \RuntimeException($this->trans('???????????????(%type%)?????????', array('%type%' => $type)));
        }

        $config = array('key' => $settings[$type.'_key'], 'secret' => $settings[$type.'_secret']);

        $client = OAuthClientFactory::create($type, $config);

        return $client;
    }

    protected function getTokenService()
    {
        return $this->getServiceKernel()->createService('User.TokenService');
    }

    protected function getSensitiveService()
    {
        return $this->getServiceKernel()->createService('SensitiveWord:Sensitive.SensitiveService');
    }

    protected function getAuthService()
    {
        return $this->getServiceKernel()->createService('User.AuthService');
    }
}
