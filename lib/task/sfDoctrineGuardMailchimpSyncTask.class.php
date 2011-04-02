<?php

/**
 * Export users to mailchimp subscribe list
 */
class sfDoctrineGuardMailchimpExportTask extends sfBaseTask
{
    /**
     * Config
     */
    protected function configure()
    {
        $this->addOptions(array(
            new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The application name', 'frontend'),
            new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'prod'),
            new sfCommandOption('connection', null, sfCommandOption::PARAMETER_REQUIRED, 'The connection name', 'radius'),
        ));

        $this->namespace        = 'guard';
        $this->name             = 'mailchimp-export';
    }

    protected function execute($arguments = array(), $options = array())
    {
        new sfDatabaseManager($this->configuration);

        $api = new MCAPI(sfConfig::get('app_mailchimp_api_key'));

        if ($api->ping()) {

            $subscribers = sfGuardUserTable::getInstance()
                ->createQuery('u')
                ->select('u.email_address AS EMAIL, u.first_name AS FNAME, u.last_name AS LNAME')
                ->execute(array(), Doctrine::HYDRATE_ARRAY);

            if (count($subscribers)) {

                $listId = $this->getListId($api);
                $update = sfConfig::get('app_mailchimp_update', true);
                $optin = sfConfig::get('app_mailchimp_optin', false);

                $this->logSection('notice', sprintf('Exporting %d subscribers to list %d', count($subscribers), $listId));

                $result = $api->listBatchSubscribe($listId, $subscribers, $optin, $update);

                if ($api->errorCode) {
                    $this->logSection('error', $api->errorMessage);
                } else {
                    $this->logSection('success', sprintf('Added: %d, Updated: %d, Errors: %d',
                        $result['add_count'], $result['update_count'], $result['error_count']));
                }

            } else {
                $this->logSection('notice', 'Nothing to export!');
            }

        } else {
            $this->logSection('error', $api->errorMessage);
        }
    }


    /**
     * Get list id by name
     *
     * @param string $name
     * @return string
     */
    protected function getListId($api)
    {
        $name = sfConfig::get('app_mailchimp_list');
        $response = $api->lists(array('name' => $name));

        if (! $api->errorCode) {
            if ($response['total']) {

                return $response['data'][0]['id'];

            } else {
                $this->logSection('error', sprintf('List %s not found!', $name));
            }
        } else {
            $this->logSection('error', $api->errorMessage);
        }
    }
}