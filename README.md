## Database ORM Handler for PHP
* This package provides more flexibility working with mysql, sqlite and pgsql database systems in PHP out of the box.
It's an extension of Moorexa Query Builder. You should check out **Moorexa** for your next amazing web app.

### How to use
* Configure your database connection settings in **Amadiify/Connection.php**
You can create multiple connection setting and switch connection during run time.
But first, a default connection must be avaliable for fallbacks.

Here is how the connection file looks like.
```php
    'default' => [
        'dsn' 		=> '{driver}:host={host};dbname={dbname};charset={charset}',
		'driver'    => 'mysql', // mysql, pgsql, sqlite
		'host' 	    => '',
		'user'      => '',
		'password'  => '',
		'dbname'    => '',
		'charset'   => 'UTF8',
		'port'      => '',
		'handler'   => 'pdo', // pdo or mysqli
		'attributes'=> true,
		'production'=> [
			'driver'  =>   'mysql',
			'host'    =>   '',
			'user'    =>   '',
			'password'  =>   '',
			'dbname'    =>   '',
		],
		'options'   => [ PDO::ATTR_PERSISTENT => true ]
     ]
     // you can add more
```

### Security
* Oh yeah, it's safe. All queries are prepared and filtered even down to raw sql statements.

Once this is done, you can **Use** this connection. see example: 
```php
use Amadiify\Client;
```

## Get Request
* perform a basic get request (select query)
```php
    // generic option
    Client::table('user')->get();
    // or
    Client::user()->get();
    // or 
    \user::get(); // some other configuration must be made for this to work.
```
## More Advance Get request
* This goes beyond the basics.
```php
    // using generic
    Client::table('user')->get('userid=?', 1);
    // or
    Client::table('user')->get('userid=?')->bind(1);
    // or
    Client::table('user')->get('username,password')->where('userid=?')->bind(1);
    // we can even perform two actions at the same time
    Client::table('user')->get('userid=?')->bind(1)->update(['username' => 'frank']);
    // get random
    Client::table('user')->get()->rand();
    // using limit
    Client::table('user')->get()->limit(0,20);
    // using order
    Client::table('user')->get()->orderby('username', 'asc')->limit(0,20);
    // and much more.
    // see the cheat sheet for more possibilities.
```

## Insert Request
* You can insert in multiples. System protects you from double records. Keeps everything unqiuely stored.
* You can apply loops and chain other actions in one line.
```php
    // lets insert something simple
    $table = Client::table('user');
    // simple first
    $table->insert(['username' => 'chris']);
    // multiple
    $table->insert(['username' => 'mack'], ['username' => 'frank']); // and much more
    // if you drop the line you need to instruct execution
    $table->insert(
        ['username' => 'mack'],
        ['username' => 'sam']
    )->go();
    // or
    $table->insert('username,password')->bind('mack', '1234');
    // or run a get after
    $table->insert('username,password')->bind('mack', '1234')->get(); // returns records.
    // and much more..
```