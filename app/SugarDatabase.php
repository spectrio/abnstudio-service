<?php

namespace App;

use DB;
use Illuminate\Database\Eloquent\Model;
use DateTime;

class SugarDatabase extends Model
{
	protected $connection = 'expand';
	
	public static function listAllAccounts() {
		//  AND accounts_cstm.dev_account_c = 0
		$results = DB::connection('expand')->select('SELECT account_c, name, id as guid, date_modified
			FROM accounts INNER JOIN accounts_cstm on accounts.id = accounts_cstm.id_c
			WHERE accounts.deleted = 0 AND accounts_cstm.status_c LIKE "%Customer%" AND accounts_cstm.dev_account_c = 0
			AND account_c IS NOT NULL
			ORDER BY accounts.name ASC');
		return $results;
	}

        public static function getAccountChannels($id = null) {
            $results = [];
            if ($id) {
                $results = DB::connection('expand')->select('SELECT nets_channels.name
                        FROM accounts INNER JOIN accounts_nets_channels_c ON accounts_nets_channels_c.accounts_n698dccounts_ida = accounts.id
                        INNER JOIN accounts_cstm ON accounts_cstm.id_c = accounts.id
                        INNER JOIN nets_channels ON nets_channels.id = accounts_nets_channels_c.accounts_n9eb4hannels_idb
                        INNER JOIN nets_channels_cstm ON nets_channels_cstm.id_c = nets_channels.id
                        WHERE accounts_cstm.status_c = "Customer" AND nets_channels.deleted = 0 AND accounts.deleted = 0 AND accounts_cstm.account_c = "'.$id.'"
                        GROUP BY nets_channels.`name`
                        ORDER BY accounts.name');
                return $results;
            } else {
                return $results;
            }
        }
}
