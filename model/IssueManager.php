<?php

/**
 * Class IssueManager
 * Manager for the issue table
 */
class IssueManager extends ManagerTable {

    // NEW ISSUE
    public function newIssue(Issue $issue, int $idCustomer): bool {

        // NEW DATE
        $newDate = new DateTime();

        // ISSUE INSERT
        $sql = "INSERT INTO issue (timestamp_issue, desc_issue, status_issue, customer_id_customer) VALUES (?,?,?,?)";
        $prepare = $this->db->prepare($sql);

        $prepare->bindValue(1, $newDate->format("Y-m-d H:i:s"), PDO::PARAM_STR);
        $prepare->bindValue(2, $issue->getDescIssue(), PDO::PARAM_STR);
        $prepare->bindValue(3, 2, PDO::PARAM_INT);
        $prepare->bindValue(4, $idCustomer, PDO::PARAM_INT);

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

    function updateIssue(Issue $issue, int $idAdmin) : bool {

        $sql = "UPDATE issue SET status_issue = ?, admin_id_admin= ? WHERE id_admin=?; ";
        $prepare = $this->db->prepare($sql);

        $prepare->bindValue(1, 1, PDO::PARAM_INT);
        $prepare->bindValue(2, $idAdmin, PDO::PARAM_INT);
        $prepare->bindValue(3, $issue->getIdIssue(), PDO::PARAM_INT);

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

}