<?php
namespace cooltronicpl\varnishcache\controller;

use cooltronicpl\varnishcache\VarnishCache;
use Craft;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\web\Controller;
use craft\web\ServiceUnavailableHttpException;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\HttpException;
use yii\web\UnauthorizedHttpException;

/**
 *
 * @link      https://cooltronic.pl
 * @copyright Copyright (c) 2023 CoolTRONIC.pl sp. z o.o.
 * @author    Pawel Potacki
 * @since     2.4.0
 *
 */
class CloudflareController extends Controller
{

    /**
     * @throws ServiceUnavailableHttpException
     * @throws UnauthorizedHttpException
     * @throws HttpException
     * @throws ForbiddenHttpException
     * @throws BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        if (Craft::$app->request->isPost) {
            if (Craft::$app->request->validateCsrfToken()) {
                return parent::beforeAction($action);
            } else {
                throw new HttpException(400, 'Invalid CSRF token.');
            }
        } else {
            throw new HttpException(405, 'Only POST requests are allowed.');
        }
    }

    /**
     * @throws HttpException
     */
    public function actionCloudflare()
    {
        $function = Craft::$app->request->getParam('function');
        if ($function == 'testCloudflare') {
            $result = $this->testCloudflare();
            return Json::encode($result);

        } else {
            throw new HttpException(400, 'Invalid function name: ' . StringHelper::toString($function));
        }
    }

    private function testCloudflare()
    {
        $zone = VarnishCache::getInstance()->getSettings()->cloudflareZone;
        $api_key = VarnishCache::getInstance()->getSettings()->cloudflareApi;
        $email = VarnishCache::getInstance()->getSettings()->cloudflareEmail;

        // Define the Cloudflare API endpoint
        $endpoint = "https://api.cloudflare.com/client/v4/zones/" . $zone;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "X-Auth-Email: $email",
            "X-Auth-Key: $api_key",
            "Content-Type: application/json",
        ));

        $response = curl_exec($ch);
        $res = json_decode($response, true);
        if (curl_errno($ch)) {
            $status = 'error';
            $message = curl_error($ch);
        } elseif ($res['success'] == false) {
            $status = 'error';
            $message = $res['errors'];
        } elseif ($res['success'] == true) {
            $status = 'success';
            $message = "Cloudflare connection successful.";
        } else {
            $status = 'error';
            $message = StringHelper::toString($res);
        }
        return [
            'status' => $status,
            'message' => $message,
        ];
    }

    public function afterAction($action, $result)
    {
        $data = Json::decode($result);

        if (isset($data)) {
            $status = $data['status'];
            $message = $data['message'];
            if ($status == 'success') {
                Craft::debug('Test Cloudflare connection - ' . StringHelper::toString($status) . '. Message: ' . StringHelper::toString($message));
            } else {
                Craft::error('Test Cloudflare connection - ' . StringHelper::toString($status) . '. Message: ' . StringHelper::toString($message));
            }
        } else {
            $data = ['status' => 'error', 'message' => 'No data'];
            $status = $data['status'];
            $message = $data['message'];
            Craft::error('Test Cloudflare connection - ' . StringHelper::toString($status) . '. Message: ' . StringHelper::toString($message));
        }

        if ($status == 'success') {
            Craft::$app->session->setNotice(StringHelper::toString($message));
        } else {
            Craft::$app->session->setError(StringHelper::toString($message));
        }

    }
}
