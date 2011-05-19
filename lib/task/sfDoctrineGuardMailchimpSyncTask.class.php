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
            new sfCommandOption('verbose', 'v', sfCommandOption::PARAMETER_NONE, 'Output error details'),
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

                $listId = sfConfig::get('app_mailchimp_list_id');
                $update = sfConfig::get('app_mailchimp_update', true);
                $optin = sfConfig::get('app_mailchimp_optin', false);

                $this->logSection('notice', sprintf('Exporting %d subscribers to list %d', count($subscribers), $listId));

                $result = $api->listBatchSubscribe($listId, $subscribers, $optin, $update);
                if ($api->errorCode) {
                    $this->logSection('error', $api->errorMessage);
                } else {
                    $this->logSection('success', sprintf('Added: %d, Updated: %d, Errors: %d',
                        $result['add_count'], $result['update_count'], $result['error_count']));

                    // http://apidocs.mailchimp.com/1.3/exceptions.field.php
                    // 212 - List_InvalidUnsubMember
                    // 213 - List_InvalidBounceMember
                    // 214 - List_AlreadySubscribed
                    if ($options['verbose'] && $result['error_count']) {
                        foreach($result['errors'] as $error) {
                            if (!in_array($error['code'], array(212, 213, 214))) {
                                $this->logSection('error', sprintf('[%s] %s: %s', $error['code'], $error['email'], $error['message']));
                            }
                        }
                    }
                }

            } else {
                $this->logSection('notice', 'Nothing to export!');
            }

        } else {
            $this->logSection('error', $api->errorMessage);
        }
    }
}
