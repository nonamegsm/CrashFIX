<?php

namespace app\models;

use Yii;
use yii\web\IdentityInterface;
use app\models\Lookup;

/**
 * This is the model class for table "tbl_user".
 *
 * @property int $id
 * @property string $username
 * @property int $usergroup
 * @property string $password
 * @property string $salt
 * @property string|null $pwd_reset_token
 * @property int $status
 * @property int $flags
 * @property string $email
 * @property int|null $cur_project_id
 * @property int|null $cur_appversion_id
 */
class User extends \yii\db\ActiveRecord implements IdentityInterface
{
    // User statuses.
    const STATUS_ACTIVE  = 1;  // This user is an active user.
    const STATUS_DISABLED = 2; // This user is a retired user.
    
    // Flags.
    const FLAG_STANDARD_USER           = 0x1;  // This user is a standard account.	
    const FLAG_PASSWORD_RESETTED       = 0x2;  // This user must change his password on login.

    public $project_member = false;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'tbl_user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['pwd_reset_token'], 'default', 'value' => null],
            [['username', 'usergroup', 'password', 'salt', 'status', 'flags', 'email'], 'required'],
            [['usergroup', 'status', 'flags'], 'integer'],
            [['username', 'password', 'salt', 'pwd_reset_token', 'email'], 'string', 'max' => 128],
            [['username'], 'unique'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Username',
            'usergroup' => 'Usergroup',
            'password' => 'Password',
            'salt' => 'Salt',
            'pwd_reset_token' => 'Pwd Reset Token',
            'status' => 'Status',
            'flags' => 'Flags',
            'email' => 'Email',
        ];
    }

    // --- IdentityInterface implementation ---

    public static function findIdentity($id)
    {
        return static::findOne($id);
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['pwd_reset_token' => $token]);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->salt; // or a dedicated auth_key field if available
    }

    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    // --- Legacy Domain Logic ---
    
    public function getGroup()
    {
        return $this->hasOne(Usergroup::className(), ['id' => 'usergroup']);
    }

    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username, 'status' => self::STATUS_ACTIVE]);
    }

    public function validatePassword($password)
    {
        return $this->hashPassword($password, $this->salt) === $this->password;
    }

    public function protectPassword()
    {
        $salt = self::randomSalt();
        $this->password = self::hashPassword($this->password, $salt);
        $this->salt = $salt;
    }

    private function hashPassword($password, $salt)
    {
        return md5($salt.$password);
    }

    private static function randomSalt($length = 32)
    {
        $chars = "abcdefghijkmnopqrstuvwxyz023456789";
        srand((double)microtime() * 1000000);
        $i = 1;
        $salt = '';

        while ($i <= $length) {
            $num = rand() % 33;
            $tmp = substr($chars, $num, 1);
            $salt .= $tmp;
            $i++;
        }
        return $salt;
    }
    
    public function isStandard()
    {
        return 0 != ($this->flags & self::FLAG_STANDARD_USER);		
    }
    
    public function isPasswordResetted()
    {
        return 0 != ($this->flags & self::FLAG_PASSWORD_RESETTED);
    }

    public function getEffectiveStatus()
    {				
        if ($this->status != self::STATUS_DISABLED) {
            $group = Usergroup::findOne($this->usergroup);
            if ($group !== null && $group->status == Usergroup::STATUS_DISABLED) {
                return self::STATUS_DISABLED;				
            }
        }
        return $this->status;
    }

    public function getEffectiveStatusStr()
    {
        $status = $this->getEffectiveStatus();
        return Lookup::item('UserStatus', $status);
    }

    public function isGroupDisabled()
    {
        return ($this->group && $this->group->status == Usergroup::STATUS_DISABLED);
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($this->isNewRecord) {
                $this->flags |= self::FLAG_PASSWORD_RESETTED;
                $this->pwd_reset_token = $this->randomSalt();		
                $this->status = self::STATUS_ACTIVE;
                if(empty($this->password)) {
                    $this->password = $this->randomSalt();
                }
                $this->protectPassword();			
            }
            return true;
        }
        return false;
    }
}
