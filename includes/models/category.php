<?php
namespace Showplan\Models;
require_once 'model.php';

class Category extends Model {

	protected static $table_name = 'categories';
	protected static $columns = ['id', 'reference', 'name', 'description'];
	
}