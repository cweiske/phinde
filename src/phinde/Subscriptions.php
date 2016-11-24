<?php
namespace phinde;

/**
 * Database table containing information about Pubsubhubbub subscriptions
 */
class Subscriptions
{
    protected $db;

    public function __construct()
    {
        $this->db = new \PDO(
            $GLOBALS['phinde']['db_dsn'],
            $GLOBALS['phinde']['db_user'],
            $GLOBALS['phinde']['db_pass']
        );
        $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); 
    }

    /**
     * Fetch a topic
     *
     * @param string $topic Topic URL
     *
     * @return false|object False if the row does not exist
     */
    public function get($topic)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM subscriptions'
            . ' WHERE sub_topic = :topic'
        );
        $stmt->execute([':topic' => $topic]);

        //fetchObject() itself returns FALSE on failure
        return $stmt->fetchObject();
    }

    /**
     * Count number of subscriptions
     *
     * @return array Array of keys with different status, number as value
     */
    public function count()
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count, sub_status FROM subscriptions'
            . ' GROUP BY sub_status'
            . ' ORDER BY sub_status'
        );
        $stmt->execute();

        $res = [];
        foreach ($stmt as $row) {
            $res[$row['sub_status']] = $row['count'];
        }

        return $res;
    }

    /**
     * Create a new subscription entry in database.
     * Automatically generates secret, capkey and lease seconds.
     *
     * This method does NOT:
     * - check for duplicates (do it yourself)
     * - return the object (fetch it yourself)
     * - send subscription requests to the hub
     *
     * @param string $topic URL to subscribe to
     *
     * @return void
     */
    public function create($topic)
    {
        $stmt = $this->db->prepare(
            'INSERT INTO subscriptions'
            . ' (sub_topic, sub_status, sub_lease_seconds, sub_expires'
            . ', sub_secret, sub_capkey, sub_created, sub_updated'
            . ', sub_pings, sub_lastping, sub_statusmessage)'
            . ' VALUES '
            . ' (:topic, "subscribing", :lease_seconds, "0000-00-00 00:00:00"'
            . ', :secret, :capkey, NOW(), NOW()'
            . ', 0, "0000-00-00 00:00:00", "")'
        );
        $stmt->execute(
            [
                ':topic'         => $topic,
                ':lease_seconds' => 86400 * 30,
                ':secret'        => bin2hex(openssl_random_pseudo_bytes(16)),
                ':capkey'        => bin2hex(openssl_random_pseudo_bytes(16)),
            ]
        );
    }

    /**
     * A subscription has been confirmed by the hub - mark it as active.
     *
     * @param integer $subId        Subscription ID
     * @param integer $leaseSeconds Number of seconds until subscription expires
     *
     * @return void
     */
    public function subscribed($subId, $leaseSeconds)
    {
        $this->db->prepare(
            'UPDATE subscriptions'
            . ' SET sub_status        = "active"'
            . '   , sub_lease_seconds = :leaseSeconds'
            . '   , sub_expires       = :expires'
            . '   , sub_updated       = NOW()'
            . ' WHERE sub_id = :id'
        )->execute(
            [
                ':leaseSeconds' => $leaseSeconds,
                ':expires' => gmdate('Y-m-d H:i:s', time() + $leaseSeconds),
                ':id' => $subId,
            ]
        );
    }

    /**
     * Mark a subscription as "unsubscribed"
     *
     * @param integer $subId Subscription ID
     *
     * @return void
     */
    public function unsubscribed($subId)
    {
        $this->db->prepare(
            'UPDATE subscriptions'
            . ' SET sub_status = "unsubscribed"'
            . '   , sub_updated = NOW()'
            . ' WHERE sub_id = :id'
        )->execute([':id' => $subId]);
    }

    public function denied($subId, $reason)
    {
        $this->db->prepare(
            'UPDATE subscriptions'
            . ' SET sub_status = "denied"'
            . '   , sub_statusmessage = :reason'
            . '   , sub_updated = NOW()'
            . ' WHERE sub_id = :id'
        )->execute([':id' => $subId, ':reason' => $reason]);
    }

    public function pinged($subId)
    {
        $this->db->prepare(
            'UPDATE subscriptions'
            . ' SET sub_pings    = sub_pings + 1'
            . '   , sub_lastping = NOW()'
            . '   , sub_updated  = NOW()'
            . ' WHERE sub_id = :id'
        )->execute([':id' => $subId]);
    }

    /**
     * Detect the hub for the given topic URL
     *
     * @param string $url Topic URL
     *
     * @return array Topic URL and hub URL. Hub URL is NULL if there is none.
     */
    public function detectHub($url)
    {
        $hue = new HubUrlExtractor();
        $hue->setRequestTemplate(new HttpRequest());
        $urls = $hue->getUrls($url);
        //we violate the spec by not requiring a self URL
        $topicUrl = isset($urls['self']) ? $urls['self'] : $url;
        $hubUrl   = isset($urls['hub'])  ? $urls['hub'] : null;

        return array($topicUrl, $hubUrl);
    }
}
?>
