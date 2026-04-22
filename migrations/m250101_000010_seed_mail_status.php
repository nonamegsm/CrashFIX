<?php

use yii\db\Migration;

/**
 * Seeds MailStatus lookup values referenced by MailQueue::getStatusStr().
 * Needed by the MailController/console so the mail/index admin grid can
 * print human-readable status names.
 */
class m250101_000010_seed_mail_status extends Migration
{
    public function safeUp()
    {
        $rows = [
            ['Pending', 'MailStatus', 1, 1],
            ['Sending', 'MailStatus', 2, 2],
            ['Sent',    'MailStatus', 3, 3],
            ['Failed',  'MailStatus', 4, 4],
        ];
        foreach ($rows as $r) {
            $this->insert('{{%lookup}}', [
                'name'     => $r[0],
                'type'     => $r[1],
                'code'     => $r[2],
                'position' => $r[3],
            ]);
        }
    }

    public function safeDown()
    {
        $this->delete('{{%lookup}}', ['type' => 'MailStatus']);
    }
}
