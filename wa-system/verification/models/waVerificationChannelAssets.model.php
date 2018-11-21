<?php

class waVerificationChannelAssetsModel extends waModel
{
    protected $table = 'wa_verification_channel_assets';

    const NAME_SIGNUP_CONFIRM_HASH = 'signup_confirmation_hash';
    const NAME_SIGNUP_CONFIRM_CODE = 'signup_confirmation_code';
    const NAME_ONETIME_PASSWORD = 'onetime_password';
    const NAME_PASSWORD_RECOVERY_HASH = 'password_recovery_hash';
    const NAME_PASSWORD_RECOVERY_CODE = 'password_recovery_code';

    public function __construct($type = null, $writable = false)
    {
        parent::__construct($type, $writable);
        $this->clearExpired();
    }

    /**
     * @param int $channel_id
     * @param string $address
     * @param string $name
     * @param string $value
     * @param null|int|string $ttl TTL
     *  if NULL, asset never expires,
     *  if int, number of seconds
     *  if string, than it pass to strtotime
     * @return bool
     */
    public function set($channel_id, $address, $name, $value, $ttl = null)
    {
        $channel_id = is_scalar($channel_id) ? (int)$channel_id : 0;
        if ($channel_id <= 0) {
            return false;
        }

        $address = is_scalar($address) ? (string)$address : '';
        if (strlen($address) <= 0) {
            return false;
        }

        $name = is_scalar($name) ? (string)$name : '';
        if (strlen($name) <= 0) {
            return false;
        }

        if (is_scalar($value)) {
            $value = (string)$value;
            if (strlen($value) <= 0) {
                return false;
            }
        } elseif (!$value) {
            return false;
        }

        $expires = null;

        if ($ttl !== null) {
            if (wa_is_int($ttl)) {
                $ttl = (int)$ttl;
                if ($ttl <= 0) {
                    return false;
                }
                $expires = date('Y-m-d H:i:s', strtotime('+' . $ttl . ' seconds'));
            } elseif (is_scalar($ttl)) {
                $ttl = trim((string)$ttl);
                if ($ttl{0} === '-') {
                    return false;
                } elseif ($ttl{0} !== '+') {
                    $ttl = '+' . $ttl;
                }
                $time = strtotime($ttl);
                if ($time <= 0 || $time === false) {
                    return false;
                }
                $expires = date('Y-m-d H:i:s', $time);
            }
        }

        return $this->insert(array(
            'channel_id' => $channel_id,
            'address' => $address,
            'name' => $name,
            'value' => $value,
            'expires' => $expires
        ), 1);
    }

    /**
     * Get and delete in once
     * @param $id
     * @return array|null
     */
    public function getOnce($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            return null;
        }
        $this->clearExpired();  // for sure
        $asset = $this->getById($id);
        if (!$asset) {
            return null;
        }
        $this->deleteById($asset['id']);
        return $asset;
    }

    public function getAsset($key)
    {
        if (wa_is_int($key)) {
            return $this->getById($key);
        } elseif (is_array($key)) {

            $field = array();

            // Build proper field (conditions) array to find asset by UNIQUE key
            // Order matters (for efficient search by using index)
            $fields = array('channel_id', 'address', 'name');
            foreach ($fields as $field_id) {
                if (array_key_exists($field_id, $key)) {
                    $field[$field_id] = $key[$field_id];
                } else {
                    // not needed field_id
                    return null;
                }
            }

            return $this->getByField($field);

        } else {
            return null;
        }
    }

    public function getByField($field, $value = null, $all = false, $limit = false)
    {
        $this->clearExpired();  // for sure
        return parent::getByField($field, $value, $all, $limit);
    }

    public function clearByContact($id)
    {
        $ids = waUtils::toIntArray($id);
        $ids = waUtils::dropNotPositive($ids);
        if (!$ids) {
            return;
        }

        $cem = new waContactEmailsModel();
        $emails = $cem->select('email')->where('contact_id IN(:ids)', array('ids' => $ids))->fetchAll(null, true);

        $cdm = new waContactDataModel();
        $phones = $cdm->select('value')->where('contact_id IN(:ids)', array('ids' => $ids))->fetchAll(null, true);

        $addresses = array_merge($emails, $phones);
        $addresses = array_unique($addresses);

        $this->deleteByField(array(
            'address' => $addresses,
        ));
    }

    protected function clearExpired()
    {
        $now = date('Y-m-d H:i:s');
        $sql = "DELETE FROM `{$this->table}` WHERE `expires` <= :datetime";
        $this->exec($sql, array('datetime' => $now));
    }
}