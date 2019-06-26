<?php
/**
 * Freeform for Craft
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2019, Solspace, Inc.
 * @link          https://docs.solspace.com/craft/freeform
 * @license       https://docs.solspace.com/license-agreement
 */

namespace Solspace\FreeformPro\Integrations\CRM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Solspace\Freeform\Library\Exceptions\Integrations\IntegrationException;
use Solspace\Freeform\Library\Integrations\CRM\AbstractCRMIntegration;
use Solspace\Freeform\Library\Integrations\DataObjects\FieldObject;
use Solspace\Freeform\Library\Integrations\IntegrationStorageInterface;
use Solspace\Freeform\Library\Integrations\SettingBlueprint;
use Solspace\Freeform\Library\Logging\LoggerInterface;

class SharpSpring extends AbstractCRMIntegration
{
    const SETTING_SECRET_KEY = 'secret_key';
    const SETTING_ACCOUNT_ID = 'account_id';
    const TITLE              = 'SharpSpring';
    const LOG_CATEGORY       = 'SharpSpring';

    /**
     * Returns a list of additional settings for this integration
     * Could be used for anything, like - AccessTokens
     *
     * @return SettingBlueprint[]
     */
    public static function getSettingBlueprints(): array
    {
        return [
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_ACCOUNT_ID,
                'Account ID',
                'Enter your Account ID here.',
                true
            ),
            new SettingBlueprint(
                SettingBlueprint::TYPE_TEXT,
                self::SETTING_SECRET_KEY,
                'Secret Key',
                'Enter your Secret Key here.',
                true
            ),
        ];
    }

    /**
     * Push objects to the CRM
     *
     * @param array $keyValueList
     *
     * @return bool
     * @throws IntegrationException
     */
    public function pushObject(array $keyValueList): bool
    {
        $contactProps = [];

        foreach ($keyValueList as $key => $value) {
            preg_match('/^(\w+)___(.+)$/', $key, $matches);

            list ($all, $target, $propName) = $matches;

            switch ($target) {
                case 'contact':
                    $contactProps[$propName] = $value;
                    break;
            }
        }


        $contactId = null;
        if ($contactProps) {
            try {
                $payload  = $this->generatePayload('createLeads', ['objects' => [$contactProps]]);
                $response = $this->getResponse($payload);

                $data = json_decode((string) $response->getBody(), true);

                $this->getLogger()->info((string) $response->getBody());

                $this->getHandler()->onAfterResponse($this, $response);

                return (isset($data['result']['error']) && (count($data['result']['error']) === 0));
            } catch (RequestException $e) {
                if ($e->getResponse()) {
                    $json = json_decode((string) $e->getResponse()->getBody());
                    $this->getLogger()->error($json, ['exception' => $e->getMessage()]);
                }
            } catch (\Exception $e) {
                $this->getLogger()->error($e->getMessage());
            }
        }

        return false;
    }

    /**
     * Check if it's possible to connect to the API
     *
     * @return bool
     * @throws IntegrationException
     */
    public function checkConnection(): bool
    {
        $payload  = $this->generatePayload('getFields', ['where' => [], 'limit' => 1]);

        try {
            $response = $this->getResponse($payload);
            $json     = json_decode((string) $response->getBody(), true);

            return isset($json['result']['field']);
        } catch (\Exception $e) {
            throw new IntegrationException($e->getMessage(), $e->getCode(), $e->getPrevious());
        }
    }

    /**
     * Fetch the custom fields from the integration
     *
     * @return FieldObject[]
     * @throws IntegrationException
     */
    public function fetchFields(): array
    {
        $response = $this->getResponse($this->generatePayload('getFields'));
        $data     = json_decode((string) $response->getBody(), true);

        $fields = [];
        if (isset($data['result']['field'])) {
            $fields = $data['result']['field'];
        }

        $fieldList = [
            new FieldObject('contact___emailAddress', 'Email', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___firstName', 'First Name', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___lastName', 'Last Name', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___website', 'Website', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___phoneNumber', 'Phone Number', FieldObject::TYPE_NUMERIC, false),
            new FieldObject(
                'contact___phoneNumberExtension', 'Phone Number Extension', FieldObject::TYPE_NUMERIC, false
            ),
            new FieldObject('contact___faxNumber', 'Fax Number', FieldObject::TYPE_NUMERIC, false),
            new FieldObject('contact___mobilePhoneNumber', 'Mobile Phone Number', FieldObject::TYPE_NUMERIC, false),
            new FieldObject('contact___street', 'Street Address', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___city', 'City', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___state', 'State', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___zipcode', 'Zip', FieldObject::TYPE_NUMERIC, false),
            new FieldObject('contact___companyName', 'Company Name', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___industry', 'Industry', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___description', 'Description', FieldObject::TYPE_STRING, false),
            new FieldObject('contact___title', 'Title', FieldObject::TYPE_STRING, false),
        ];

        foreach ($fields as $field) {
            if (!$field || !\is_object($field) || $field->readOnlyValue || $field->hidden || $field->calculated) {
                continue;
            }

            $type = null;
            switch ($field->dataType) {
                case 'text':
                case 'string':
                case 'picklist':
                case 'phone':
                case 'url':
                case 'textarea':
                case 'country':
                case 'checkbox':
                case 'date':
                case 'bit':
                case 'hidden':
                case 'state':
                case 'radio':
                case 'datetime':
                    $type = FieldObject::TYPE_STRING;
                    break;

                case 'int':
                    $type = FieldObject::TYPE_NUMERIC;
                    break;

                case 'boolean':
                    $type = FieldObject::TYPE_BOOLEAN;
                    break;
            }

            if (null === $type) {
                continue;
            }

            $fieldObject = new FieldObject(
                $field->systemName,
                $field->label,
                $type,
                false
            );

            $fieldList[] = $fieldObject;
        }

        return $fieldList;
    }

    /**
     * A method that initiates the authentication
     */
    public function initiateAuthentication()
    {
    }

    /**
     * Perform anything necessary before this integration is saved
     *
     * @param IntegrationStorageInterface $model
     *
     * @throws IntegrationException
     */
    public function onBeforeSave(IntegrationStorageInterface $model)
    {
        $accountId = $this->getAccountID();
        $secretKey = $this->getSecretKey();

        // If one of these isn't present, we just return void
        if (!$accountId || !$secretKey) {
            return;
        }

        $model->updateSettings($this->getSettings());
    }

    /**
     * Gets the API secret for SharpSpring from settings config
     *
     * @return mixed|null
     * @throws IntegrationException
     */
    private function getSecretKey()
    {
        return $this->getSetting(self::SETTING_SECRET_KEY);
    }

    /**
     * Gets the account ID for SharpSpring from settings config
     *
     * @return mixed|null
     * @throws IntegrationException
     */
    private function getAccountID()
    {
        return $this->getSetting(self::SETTING_ACCOUNT_ID);
    }

    /**
     * Get the base SharpSpring API URL
     *
     * @return string
     */
    protected function getApiRootUrl(): string
    {
        return 'https://api.sharpspring.com/pubapi/v1.2/';
    }

    /**
     * Generate a properly formatted payload for SharpSpring API
     *
     * @param string $method
     * @param array  $params
     * @param string $id
     *
     * @return array
     */
    private function generatePayload($method, array $params = ['where' => []], $id = 'freeform'): array
    {
        return [
            'method' => $method,
            'params' => $params,
            'id'     => $id,
        ];
    }

    /**
     * Authorizes the application
     * Returns the access_token
     */
    public function fetchAccessToken(): string
    {
        return '';
    }

    /**
     * @param array $payload
     *
     * @return ResponseInterface
     */
    private function getResponse(array $payload): ResponseInterface
    {
        $client = new Client();

        return $client->post(
            $this->getApiRootUrl(),
            [
                'query' => [
                    'accountID' => $this->getAccountID(),
                    'secretKey' => $this->getSecretKey(),
                ],
                'json'  => $payload,
            ]
        );
    }
}
