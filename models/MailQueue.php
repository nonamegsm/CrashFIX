<?php

namespace app\models;

use Yii;
use app\models\Lookup;

/**
 * This is the model class for table "tbl_mail_queue".
 *
 * @property int $id
 * @property int $create_time
 * @property int $status
 * @property int|null $sent_time
 * @property string $recipient
 * @property string $email_subject
 * @property string $email_headers
 * @property string $email_body
 */
class MailQueue extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_mail_queue';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['sent_time'], 'default', 'value' => null],
            [['create_time', 'status', 'recipient', 'email_subject', 'email_headers', 'email_body'], 'required'],
            [['create_time', 'status', 'sent_time'], 'integer'],
            [['email_body'], 'string'],
            [['recipient', 'email_headers'], 'string', 'max' => 1024],
            [['email_subject'], 'string', 'max' => 256],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'create_time' => 'Create Time',
            'status' => 'Status',
            'sent_time' => 'Sent Time',
            'recipient' => 'Recipient',
            'email_subject' => 'Email Subject',
            'email_headers' => 'Email Headers',
            'email_body' => 'Email Body',
        ];
    }

    public function getStatusStr()
    {
        return Lookup::item('MailStatus', $this->status);
    }
}
