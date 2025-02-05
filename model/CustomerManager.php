<?php

/**
 * Class CustomerManager
 * Manager for the customer table
 */
class CustomerManager extends ManagerTable {

    // CREATING NEW CUSTOMER
    public function newCustomer(Customer $customer): bool {

        // CUSTOMER INSERT
        $sql = "INSERT INTO monitoring_hungry_nuggets_customer (name_customer, domain_customer, hosting_customer, contact_person_customer, mail_customer, phone_customer) VALUES (?,?,?,?,?,?)";
        $prepare = $this->db->prepare($sql);

        try {

            $prepare->execute([
                $customer->getNameCustomer(),
                $customer->getDomainCustomer(),
                $customer->getHostingCustomer(),
                $customer->getContactPersonCustomer(),
                $customer->getMailCustomer(),
                $customer->getPhoneCustomer()
            ]);

            // IF OKAY
            return true;

        } catch (Exception $e) {

            trigger_error($e->getMessage());

            // IF NOT
            return false;
        }
    }

    function updateCustomer(Customer $customer, int $idCustomer) : bool {

        $sql = "UPDATE monitoring_hungry_nuggets_customer SET name_customer= ?, domain_customer= ?, hosting_customer = ?, contact_person_customer= ?, mail_customer= ?, phone_customer= ? WHERE id_customer=?; ";
        $prepare = $this->db->prepare($sql);

        $prepare->bindValue(1, $customer->getNameCustomer(), PDO::PARAM_STR);
        $prepare->bindValue(2, $customer->getDomainCustomer(), PDO::PARAM_STR);
        $prepare->bindValue(3, $customer->getHostingCustomer(), PDO::PARAM_STR);
        $prepare->bindValue(4, $customer->getContactPersonCustomer(), PDO::PARAM_STR);
        $prepare->bindValue(5, $customer->getMailCustomer(), PDO::PARAM_STR);
        $prepare->bindValue(6, $customer->getPhoneCustomer(), PDO::PARAM_STR);
        $prepare->bindValue(7, $idCustomer, PDO::PARAM_INT);

        try {

            $prepare->execute();

            // IF OKAY
            return true;

        } catch (Exception $e) {

            trigger_error($e->getMessage());

            // IF NOT
            return false;
        }
    }

    // SELECT ALL CUSTOMERS
    public function selectAllCustomers(): array {

        $sql = "SELECT * FROM monitoring_hungry_nuggets_customer ORDER BY name_customer ASC;";
        $query = $this->db->query($sql);

        // IF THERE IS AT LEAST 1 RESULT
        if ($query->rowCount()) {
            return $query->fetchAll(PDO::FETCH_ASSOC);
        }

        // IF NOT
        return [];
    }

    // SELECT ONE CUSTOMER
    public function selectOneCustomer(int $idCustomer): array {
        $sql = "SELECT * FROM monitoring_hungry_nuggets_customer WHERE id_customer = ?";
        $prepare = $this->db->prepare($sql);

        try {

            $prepare->execute([$idCustomer]);

            // IF THERE IS A RESULT
            if ($prepare->rowCount()) {

                return $prepare->fetch(PDO::FETCH_ASSOC);

            // IF NOT
            } else {
                return [];
            }

        } catch (Exception $e) {

            trigger_error($e->getMessage());
            // IF NOT
            return [];

        }
    }

    public function deleteCustomer(int $idCustomer) : bool {

        // PREPARE DELETION FOR ISSUES
        $sqlIssue = "DELETE FROM monitoring_hungry_nuggets_issue WHERE customer_id_customer= ?";
        $prepareIssue = $this->db->prepare($sqlIssue);
        // PREPARE DELETION FOR CUSTOMER
        $sqlCustomer = "DELETE FROM monitoring_hungry_nuggets_customer WHERE id_customer= ?";
        $prepareCustomer = $this->db->prepare($sqlCustomer);

        try {

            // TRANSACTION
            $this->db->beginTransaction();

            $prepareIssue->execute([$idCustomer]);

            $prepareCustomer->execute([$idCustomer]);

            // IF OKAY
            $this->db->commit();
            return true;

        } catch (Exception $e) {

            trigger_error($e->getMessage());

            // IF NOT
            $this->db->rollBack();
            return false;
        }

    }

    // DNS STATUS WITH OVH API
    public function dnsStatus($ovh, $transport, string $domain,int $idCustomer,IssueManager $issueManager) : bool {

        try {
            // API
            return $ovh->get('/domain/zone/'.$domain.'/status')['isDeployed'];

            // IF SOMETHING WRONG
        } catch (GuzzleHttp\Exception\ClientException $e) {

            // CHECK IF THERE IS ONGOING ISSUE FOR THIS CUSTOMER
            $ongoingIssue = $issueManager->ongoingIssue($idCustomer);

            // IF THERE IS
            if ($ongoingIssue) {

                return false;

                // IF NOT
            } else {

                // NEW ISSUE
                $issueManager->newIssue("DNS down", $idCustomer);
                // MAIL
                self::mailOnIssue($transport);
                // NEW MAIL
                return false;

            }
        }
    }

    // SERVER STATUS WITH OVH API
    public function serverStatus($ovh, $transport, string $hosting,int $idCustomer,IssueManager $issueManager) : bool {

        // API
        $apiIncident = $ovh->get('/hosting/web/incident');

        try {
            // API
            $apiActive = $ovh->get('/hosting/web/'.$hosting)['state'];

            // IF SOMETHING WRONG
        } catch (GuzzleHttp\Exception\ClientException $e) {

            return false;

        }

        // IF THE SERVICE IS ACTIVE AND THERE IS NO ERRORS
        if (empty($apiIncident) && $apiActive === 'active'){

            return true;

        } else {

            // CHECK IF THERE IS ONGOING ISSUE FOR THIS CUSTOMER
            $ongoingIssue = $issueManager->ongoingIssue($idCustomer);

            // IF THERE IS
            if ($ongoingIssue) {

                return false;

                // IF NOT
            } else {

                // NEW ISSUE
                $issueManager->newIssue("Server down", $idCustomer);
                // MAIL
                self::mailOnIssue($transport);
                return false;

            }
        }
    }

    // SERVER FULL STATUS WITH OVH API
    public function serverFullStatus($ovh, string $domain, string $hosting) : array {

        try {

            // API
            return $ovh->get('/hosting/web/'.$hosting.'/attachedDomain/'.$domain);

            // IF SOMETHING WRONG
        } catch (GuzzleHttp\Exception\ClientException $e) {

            return [];

        }
    }

    // SERVER SPECIFICATION FULL STATUS WITH OVH API
    public function serverSpeFullStatus($ovh, string $hosting) : array {

        try {

            // API
            return $ovh->get('/hosting/web/'.$hosting);

            // IF SOMETHING WRONG
        } catch (GuzzleHttp\Exception\ClientException $e) {

            return [];

        }
    }

    // DOMAIN STATUS WITH OVH API
    public function domainStatus($ovh, $transport, string $domain,int $idCustomer,IssueManager $issueManager) : bool {

        try {
            // API
            $api = $ovh->get('/domain/zone/'.$domain.'/serviceInfos')['status'];

            if ($api === 'ok') {

                return true;

            } else {

                // CHECK IF THERE IS ONGOING ISSUE FOR THIS CUSTOMER
                $ongoingIssue = $issueManager->ongoingIssue($idCustomer);

                // IF THERE IS
                if ($ongoingIssue) {

                    return false;

                    // IF NOT
                } else {

                    // NEW ISSUE
                    $issueManager->newIssue("Domain related problem", $idCustomer);
                    // MAIL
                    self::mailOnIssue($transport);
                    return false;

                }
            }

            // IF SOMETHING WRONG
        } catch (GuzzleHttp\Exception\ClientException $e) {

            return false;

        }
    }

    // DOMAIN FULL STATUS WITH OVH API
    public function domainFullStatus($ovh, string $domain) : array {

        try {

            // API
            return $ovh->get('/domain/zone/'.$domain.'/serviceInfos');

            // IF SOMETHING WRONG
        } catch (GuzzleHttp\Exception\ClientException $e) {

            return [];

        }
    }

    // MAIL ON NEW ISSUE
    private function mailOnIssue($transport) {

        $adminManager = new AdminManager($this->db);
        $admins = $adminManager->selectValidatedAdmins();
        $adminList= [];
        foreach ($admins as $admin) {
            $adminList[] = $admin['mail_admin'];
        }

        // MAIL FOR CONFIRMATION
        $mail = new Swift_Mailer($transport);
        $message = (new Swift_Message('Nouveau soucis détecté sur le monitoring Hungry Nuggets'))
            ->setFrom([MAIL_ADDRESS => 'Hungry Nuggets'])
            ->setTo($adminList);

        // IMAGES
        $imageMain = $message->embed(Swift_Image::fromPath('img/mails/entete-mail.jpg'));
        $imageText = $message->embed(Swift_Image::fromPath('img/mails/new-issue.png'));
        $imageFooter = $message->embed(Swift_Image::fromPath('img/mails/bottom-mail.png'));

        // SET MAIL BODY
        $message->setBody(
            MailManager::mailIssue(["imgTop"=>$imageMain,"imgText"=>$imageText,"imgBottom"=>$imageFooter]),
            'text/html'
        );

        $mail->send($message);
    }
}