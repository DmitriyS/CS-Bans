<?php
/**
 * @author Craft-Soft Team
 * @package CS:Bans
 * @version 1.0 beta
 * @copyright (C)2013 Craft-Soft.ru.  Все права защищены.
 * @link http://craft-soft.ru/
 * @license http://creativecommons.org/licenses/by-nc-sa/4.0/deed.ru  «Attribution-NonCommercial-ShareAlike»
 */

/**
 * Модель для таблицы "{{amxadmins}}".
 *
 * Доступные поля таблицы '{{amxadmins}}':
 * @property integer $id ID админа
 * @property string $username имя админа
 * @property string $password Пароль админа
 * @property string $access Доступ
 * @property string $flags Флаги
 * @property string $steamid Стим
 * @property string $nickname Ник
 * @property integer $icq Контакты
 * @property integer $ashow Показывать ли на странице админов
 * @property integer $created Дата добавления
 * @property integer $expired Дата окончания
 * @property integer $days Дней админки
 */
class Amxadmins extends CActiveRecord
{
	//public $accessflags = array();
	public $change;
	public $addtake = null;
	//public $servers;
    const FLAG_VIP = 't';
    const FLAG_PLAYER = 'z';

	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return '{{amxadmins}}';
	}


	public function getAccessflags()
    {
		return str_split($this->access);
	}

	public function setAccessflags($value)
    {
		//return false;
	}

	public function scopes()
    {
        return array(
            'sort'=>array(
                'order'=>'`access` ASC, `id` ASC'
            ),
        );
    }

	public function rules()
	{
		return array(
			array('nickname, icq', 'required'),
			array('accessflags, addtake, servers', 'safe'),
			array('ashow, is_active, days, change', 'numerical', 'integerOnly'=>true),
			array('username, access, flags, steamid, nickname', 'length', 'max'=>32),
			array('password, icq', 'length', 'max'=>50),
			array('id, username, password, access, flags, steamid, nickname, icq, ashow, created, last_seen, expired, days', 'safe',  'on'=>'search'),
		);
	}

	public function relations()
	{
		return array(
			'servers' => array(
				self::MANY_MANY,
				'Serverinfo',
				'{{admins_servers}}(admin_id, server_id)'
			),
		);
	}

	public function attributeLabels()
	{
		return array(
			'id' => 'ID',
			'username' => 'SteamID/IP/Ник',
			'password' => 'Пароль',
			'access' => 'Доступ',
			'accessflags' => 'Флаги доступа',
			'flags' => 'Тип админки',
			'steamid' => 'SteamID',
			'nickname' => 'Аккаунт',
			'icq' => 'Контакты',
			'ashow' => 'Показывать в списке админов',
            'is_active' => 'Активирован на сервере',
			'created' => 'Дата добавления',
            'last_seen' => 'Последний визит',
			'expired' => 'Истекает',
			'days' => 'Дней',
			'long' => 'Осталось дней',
			'change' => 'Новый срок',
			'addtake' => 'Выбор',
			'servers' => 'Назначить на серверах',
		);
	}

	public function search()
	{
		$criteria=new CDbCriteria;

		$criteria->compare('id',$this->id);
		$criteria->compare('username',$this->username,true);
		$criteria->compare('password',$this->password,true);
		$criteria->compare('access',$this->access,true);
		$criteria->compare('flags',$this->flags,true);
		$criteria->compare('steamid',$this->steamid,true);
		$criteria->compare('nickname',$this->nickname,true);
		$criteria->compare('icq',$this->icq,true);
		$criteria->compare('ashow',$this->ashow);
        $criteria->compare('is_active',$this->ashow);
		$criteria->compare('created',$this->created);
        $criteria->compare('last_seen',$this->last_seen);
		$criteria->compare('expired',$this->expired);
		$criteria->compare('days',$this->days);
		$criteria->order = '`access` ASC, `id` ASC';

		return new CActiveDataProvider($this, array(
			'criteria'=>$criteria,
			'pagination' => array(
				'pageSize' => Yii::app()->config->bans_per_page,
			),
		));
	}

	public static function getList() {
		$admins = self::model()->findAll('`is_active` = 1 AND `ashow` = 1');

		$list = array();
		foreach($admins AS $admin) {
			$list[$admin->nickname] = $admin->nickname;
		}

		return $list;
	}

	public static function getFlags($adminlist = false)
	{
		if($adminlist)
		{
			return array(
				'abcdefghjkmnopqrstu' => 'Админ',
				't' => 'Вип',
				'z' => 'Игрок'
			);
		}

		return array(
			'abcdefghjkmnopqrstu' => 'Админ',
			't' => 'Вип',
			'z' => 'Игрок'
		);
	}

	protected function beforeDelete() {
		parent::beforeDelete();
		$servers = AdminsServers::model()->findByAttributes(array('admin_id' => $this->id));
		if ($servers !== null) {
            $servers->deleteAllByAttributes(array('admin_id' => $this->id));
        }

        return true;
	}

	protected function beforeSave() {
        $removePwd = filter_input(INPUT_POST, 'removePwd', FILTER_VALIDATE_BOOLEAN);
        if($removePwd) {
            $this->password = '';
        }

		if($this->isNewRecord)
        {
			$this->created = time();
            if($this->password && $this->scenario != 'buy') {
                $this->password = md5($this->password);
                //$this->password = hash('sha256', $this->password);
            }
            if($this->flags != 'a' && !$this->password) {
                $this->flags .= 'e';
            }
			$this->expired = $this->days != 0 ? ($this->days * 86400) + time() : 0;
		}
        else
        {
			if ($this->password) {
                $this->password = md5($this->password);
                //$this->password = hash('sha256', $this->password);
            } else {
                $oldadmin = Amxadmins::model()->findByPk($this->id);
                if ($oldadmin->password && !$removePwd) {
                    $this->password = $oldadmin->password;
                } elseif($this->flags != 'a') {
                    $this->flags .= 'e';
                }
            }

            if($this->expired == 0) {
				$this->expired = time();
			}

			switch($this->addtake) {
				case '1':
					$this->expired = $this->expired - ($this->change *86400);
					$this->days = $this->days - $this->change;
					break;
				case '0':
					$this->expired = $this->expired + ($this->change *86400);
					$this->days = $this->days + $this->change;
					break;
				default:
					$this->expired = 0;
					$this->days = 0;
			}
		}
		return parent::beforeSave();
	}

	protected function afterValidate() {

		if ($this->scenario == 'buy') {
            return true;
        }

        if (!$this->access) {
            $this->addError('access', 'Выберите флаги доступа');
        }

        if($this->isNewRecord && $this->flags === 'a' && !$this->password) {
            $this->addError('password', 'Для админки по нику нужно обязательно указывать пароль');
        }

		if ($this->flags === 'd' && !Prefs::validate_value($this->username, 'ip')) {
            $this->addError('username', 'Неверно введен IP');
        }

        if ($this->flags === 'c' && !Prefs::validate_value($this->username, 'steamid')) {
            $this->addError('username', 'Неверно введен SteamID');
        }

/*
        if ($this->password && !preg_match('#^([a-z0-9]+)$#i', $this->password)) {
			$this->addError ('password', 'Пароль может содержать только буквы латинского алфавита и цифры');
		}
*/

        if(!$this->isNewRecord && $this->days < $this->change && $this->addtake === '1')
		{
			$this->addError ('', 'Ошибка! Нельзя забрать дней больше, чем у него уже есть');
		}

/*
        if(empty($this->servers)) {
            $this->addError ('servers', 'Выберите хотябы один сервер');
        }
*/

        if($this->hasErrors()) {
            return $this->getErrors();
        }

		return parent::afterValidate();
	}

	public static function getAuthType($get = false)
	{
		$flags = array(
			'a' => 'Ник',
			'c' => 'SteamID',
			'd' => 'IP'
		);
		if($get) {
            $flag = $get{0};
			if(isset($flags[$flag])) {
                $return = $flags[$flag];
                if(!isset($get{1})) {
                    $return .= ' + пароль';
                }
				return $return;
			}
			return 'Неизвестно';
		}
		return $flags;
	}

	public function getLong()
	{
		$long = $this->expired - time();
		if ($this->expired == 0 || $long < 0) {
            return false;
        }

        return intval($long / 86400);
	}

    public static function getRole($flags)
    {
        if ($flags == Amxadmins::FLAG_VIP)
        {
            return 'ВИП';
        }
        elseif ($flags == Amxadmins::FLAG_PLAYER)
        {
            return 'ИГРОК';
        }
        else
        {
            return 'АДМИН';
        }
    }

	public function afterSave() {
		if(!empty($this->servers) && $this->isNewRecord) {
			foreach($this->servers as $is) {
				$inservers = new AdminsServers;
				$inservers->unsetAttributes();
				if (!Serverinfo::model()->findByPk($is)) {
                    continue;
                }

                $inservers->admin_id = $this->id;
				$inservers->server_id = intval($is);
				$inservers->use_static_bantime = 'no';
				if (!$inservers->save()) {
                    continue;
                }
            }
		}

		if ($this->isNewRecord) {
            Syslog::add(Logs::LOG_ADDED, 'Добавлен новый AmxModX админ <strong>' . $this->nickname . '</strong>');
        } else {
            Syslog::add(Logs::LOG_EDITED, 'Изменены детали AmxModX админа <strong>' . $this->nickname . '</strong>');
        }
        return parent::afterSave();
	}

	public function afterDelete() {
        AdminsServers::model()->deleteAllByAttributes(array('admin_id' => $this->id));
		Syslog::add(Logs::LOG_DELETED, 'Удален AmxModX админ <strong>' . $this->nickname . '</strong>');
		return parent::afterDelete();
	}
}
