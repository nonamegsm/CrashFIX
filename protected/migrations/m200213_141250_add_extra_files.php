<?php

class m200213_141250_add_extra_files extends CDbMigration
{
	public function up()
	{
		$this->createTable('{{extra_files}}',
					array(
						'id'=>'pk',
						'project_id'=>'INTEGER NOT NULL',
						'name' =>'VARCHAR(128) NOT NULL',
						'date_from'=>'INTEGER',						
						'date_to'=>'INTEGER',
						'status'=>'INTEGER NOT NULL',
						'path'=>'VARCHAR(128)',
					)
				);
	}

	public function down()
	{
		$this->dropTable('{{extra_files}}');
		return true;
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