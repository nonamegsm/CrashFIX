<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Read-only ActiveRecord backed by the SQL view
 * `view_serials_report_count` (created in migration
 * m260423_000003_create_view_serials_report_count).
 *
 * Each row represents one (box_serial, card_serial) pair from
 * uploaded crash reports' custom-properties, plus the number of
 * distinct crash reports that pair has produced.
 *
 * The view is not writable, so save / insert / delete are never
 * called against this model. Yii2 ActiveRecord still works fine
 * for read paths (find/where/all/dataProvider) over a view as
 * long as findOne()/save() aren't attempted - which they're not
 * in any caller.
 *
 * Composite "primary key" hint is required by Yii2 to dedupe
 * rows in some grids; we use the (box_serial, card_serial)
 * pair since the view itself groups on those two columns.
 *
 * @property string $box_serial
 * @property string $card_serial
 * @property int    $report_count
 */
class Serialsinfo extends ActiveRecord
{
    public static function tableName()
    {
        // Intentionally NOT prefixed with the configured table prefix:
        // the underlying view (created by the migration) is named
        // 'view_serials_report_count' so both Yii1 and Yii2 frontends
        // can share it. See the migration's NOTE.
        return 'view_serials_report_count';
    }

    /**
     * The view has no real PK. Treating the (box_serial, card_serial)
     * tuple as a composite PK lets Yii2 dedupe rows in CGridView
     * results without colliding on identical content.
     */
    public static function primaryKey()
    {
        return ['box_serial', 'card_serial'];
    }

    public function rules()
    {
        return [
            [['box_serial', 'card_serial'], 'string', 'max' => 128],
            [['report_count'], 'integer'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'box_serial'   => 'Box Serial',
            'card_serial'  => 'Card Serial',
            'report_count' => 'Report Count',
        ];
    }
}
