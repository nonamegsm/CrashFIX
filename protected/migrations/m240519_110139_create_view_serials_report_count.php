<?php

class m240519_110139_create_view_serials_report_count extends CDbMigration
{
	public function up()
    {
        $this->execute("
            CREATE VIEW `view_serials_report_count` AS
            SELECT 
                box.`value` AS `box_serial`,
                card.`value` AS `card_serial`,
                COUNT(DISTINCT cr.`id`) AS `report_count`
            FROM 
                `customprop` box
            JOIN 
                `customprop` card ON box.`crashreport_id` = card.`crashreport_id`
            JOIN 
                `crashreport` cr ON box.`crashreport_id` = cr.`id`
            WHERE 
                box.`name` = 'Box Serial'
                AND card.`name` = 'Card Serial'
            GROUP BY 
                box.`value`, card.`value`;
        ");
    }

	public function down()
    {
        $this->execute("DROP VIEW IF EXISTS `view_serials_report_count`");
    }
	/*
	// Use safeUp/safeDown to do migration with transaction
	public function safeUp()
	{
	}

	public function safeDown()
	{
	}
	*/
}