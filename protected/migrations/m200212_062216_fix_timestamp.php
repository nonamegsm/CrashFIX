<?php

class m200212_062216_fix_timestamp extends CDbMigration
{
	public function up()
	{
        $this->alterColumn('{{module}}', 'timestamp', 'BIGINT');
        $this->alterColumn('{{bug_change}}', 'timestamp', 'BIGINT');
        $this->alterColumn('{{operation}}', 'timestamp', 'BIGINT');
	}

	public function down()
	{
		echo "m200212_062216_fix_timestamp does not support migration down.\n";
		return false;
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