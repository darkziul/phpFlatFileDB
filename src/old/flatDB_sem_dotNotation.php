<?php

namespace darkziul;

// use darkziul\Helpers\dotNotationArrayAccess as DNAA;
use darkziul\Helpers\directory;
use \Exception as Exception;


class flatDB
{
	/**
	 * @var array
	 */
	private $query = [
		'table' => null,
		'where' => null,
		'order' => ['by' => 'id', 'type' => 'desc'],
		'limit' => 0,
		'offset' => 0,
		'select' => null
	];

	/**
	 * var responsabel pelas info do db
	 * @var array
	 */
	private $db = [];

	
	/**
	 * @var obj
	 */
	private $directoryInstance;
	/**
	 * @var string
	 */
	private $strDenyAccess =  '<?php return header("HTTP/1.0 404 Not Found"); exit(); //Negar navegação | Deny navigation ?>';
	/**
	 * @var number
	 */
	private $strlenDenyAccess;
	/**
	 * @var string
	 */
	private $metaBaseName	 = '_metadata.php';

	/**
	 * armazenar conteudo para ser executado
	 * @var array
	 */
	private $prepare = [
		'method' => null,
		'id' => 0,
		'data' => null,
		'meta' => null
	];
	/**
	 * Prefixo do cache
	 * @var string
	 */
	private $cacheNameDir = '.cache';


	private static $accessArray;

	/**
	 * construtor
	 * @param string|null $dirInit  Diretorio do armazenamento dos arquivos
	 * @return type
	 */
	public function __construct($dirInit = null) {

		$this->directoryInstance = new directory();

		$nameDataDBdefault = '__data.flatdb';
		$this->db['basePath'] = is_null($dirInit) ? $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/' . $nameDataDBdefault . '.storage/' : $dirInit;
		$this->directoryInstance->create($this->db['basePath']);//cria o dir


		$this->strlenDenyAccess = strlen($this->strDenyAccess); //calcular o tamanho da string
	}


	/**
	 * Retorna nome formatado
	 * @param type $dbName 
	 * @return type
	 */
	private function getDbName($dbName)
	{
		return $dbName  . '.' . $this->simplesHash($dbName) . '.db';
	}

	/**
	 * Gerar o caminho do diretorio do banco
	 * @param string $dbPath 
	 * @param string $dbName 
	 * @return string  caminho construído
	 */
	private function getDbPath($dbName, $dbPath=null)
	{
		// if (empty($dbName)) $dbName = $this->db['name']; //pegaNameDefault
		$dbName = $this->getDbName($dbName);//formatar nome

		if (empty($dbPath)) return $this->db['basePath'] . $dbName . '/'; //Folder default
		else  return $dbPath . '/' . $dbName . '/';
	}



	/**
	 * Identificar a DataBase para consulta
	 * @param type|null $dbName nome da DB
	 * @return this
	 */
	public function db($dbName = null)
	{
		if (!$this->dbExists($dbName)) throw new Exception(sprintf('Nao existe a tabela "%s".', $dbName));

		// $this->db['basePath']
		$this->db['instantiated'] = true; // set instantiated
		$this->db['name'] = $dbName;//set name
		$this->db['path'] =  $this->getDbPath($dbName);//set path

		//Encadeamento | chaining
		return $this;
	}
	/**
	 * Criar o DB
	 * @param string|null $dbName Nome do DB
	 * @return bool
	 */
	public function dbCreate($dbName = null)
	{

		if(strlen(''.$dbName) < 3 ) throw new Exception(sprintf('"%s" precisa ter no minimo 3 caracteres', $dbName));
		$dbPath = $this->getDbPath($dbName);
		if( !$this->directoryInstance->create($dbPath) ) throw new Exception(sprintf('Não foi possível crear o diretorio do DB: "%s"!', $dbPath));
		// return $this->write($dbPath . 'index.php', '', false);//criar um index apenas com o codigo de 404
		return true;//criar um index apenas com o codigo de 404
	}


	/**
	 * Deletar DB
	 * @param string $dbName 
	 * @param string|null $dbPath caminho do diretorio @example data/example/dir/
	 * @return bool|null  NULL quando $dir não for um diretorio
	 */
	public function dbDelete($dbName = null)
	{
		
		if ($this->dbExists($dbName))	{
			return $this->directoryInstance->delete($this->getDbPath($dbName));
		}

		return null;
	}



	/**
	 * Identificar se DB existe
	 * @param string $dbName 
	 * @param type|null $dbPath 
	 * @return type
	 */
	public function dbExists($dbName = null)
	{
		$dir = $this->getDbPath($dbName);
		return is_dir($dir);
	}


	/**
	 * Mostrar todas as tabelas do DB atual
	 * @param bool $jsonOUT TRUE: saida sera em string JSON 
	 * @return string|array  Default eh Array
	 */
	public function tableShow($jsonOUT = false)
	{
		if (!$this->db['instantiated']) throw new Exception('Nao ha nenhum DB setado!');

		$data = $this->directoryInstance->showFolders($this->db['path']);

		if ($jsonOUT) return json_encode($data);//string json
		return $data;//array
	}



	/**
	 * Setar Tabela
	 * @param string $name  Nome da Tabela
	 * @param  bool $create TRUE: cria a tabela caso não exista | Create case does not exist
	 * @return this
	 */
	public function table($name, $create=false)
	{
		if (!$this->db['instantiated']) throw new Exception('Nao existe DataBase selecionado!');

		if (!$this->tableExists($name) && $create) $this->tableCreate($name);
		elseif (!$this->tableExists($name)) throw new Exception(sprintf('Nao existe a tabela "%s"', $name));

		// set query =====
		// ===============
		$this->query['table'] = $name;
		$this->query['tablePath'] = $this->getTablePath($name);
		$this->query['where'] = [];
		$this->query['order'] = ['by'=>'id', 'type'=>'desc'];
		$this->query['limit'] = 0;
		$this->query['offset'] = 0;
		$this->query['select'] = null;

		return $this;
	}

	/**
	 * Criar tabela
	 * @param string $name  Nome da tabela
	 * @return bool
	 */
	public function tableCreate($name)
	{
		if(strlen(''.$name) < 3 ) throw new Exception(sprintf('"%s" precisa ter no minimo 3 caracteres', $name));
		

		$path = $this->getTablePath($name);
		$this->query['tablePath'] = $path;// antecipar set

		if( !$this->directoryInstance->create($path) ) throw new Exception(sprintf('Não foi possível crear o diretorio da Tabela: "%s"!', $path));
		var_dump($this->getPathCache());
		$this->directoryInstance->create($this->getPathCache());//criar a pasta cache
		return true;
	}

	/**
	 * Deletar tabela
	 * @param type $name 
	 * @return null|bool NULL: caso nao existe tabela
	 */
	public function tableDelete($name)
	{

		if ($this->tableExists($name)) {
			return $this->directoryInstance->delete($this->getTablePath($name));
		}

		return null;
	}
	/**
	 * Saber se existe a tabela
	 * @param string $name nome da tabela a ser consultada
	 * @return bool
	 */
	public function tableExists($name)
	{
		return is_dir($this->getTablePath($name));
	}

	/**
	 * Gerar o caminho para tabela
	 * @param string $name  nome da tabela
	 * @return string CAminho completo da tabela
	 */
	private function getTablePath($name)
	{
		$name  =  $name . '.' . $this->simplesHash($name) . '.tb';
		return $this->db['path'] . $name . '/';
	}





	/**
	 * Adcionar conteudo
	 * @param array $array 
	 * @param  mixed $nameID Caso seja necessario ADICIONAR um ID personalizado.
	 * @return this
	 */
	public function insert(array $array)
	{
		$id = 1;
		$meta = [];

		if (!$this->hasMeta()) {
			$meta['length'] = $id;
		} else {
			$meta = $this->getMeta();
			$meta['length'] = count($meta['indexes'])+1;
			$id = end($meta['indexes'])+1;
		}

		$array['id'] = $id;
		$meta['lastId'] = $id;
		$meta['indexes'][$id] = $id;


		// execute data ==========
		$this->prepareSet('insert', $array), $id, $meta);

		return $this;//encadeamento
	}

	/**
	 * Deletar arquivo(item)
	 * @param number|null $id Id a ser deletado ou nao setar para ser usado no metodo WHERE 
	 * @return this
	 */
	public function delete($id=null) 
	{
		if (is_null($id)) {
			$this->prepareSet('removeByWhere');
		}
		else $this->prepareSet('removeById', null, (array)$id);	
		return $this; //encadeamento
	}

	public function select($key=null)
	{
		$this->query['select'] = (array)$key;

		$this->prepareSet('select');

		return $this;
	}


	/**
	 * Executar o métodos
	 * @return bool|null NULL quando nao foi executado nenhum metodo
	 */
	public function execute()
	{
		$executed = false;
		if ('insert' === $this->prepareGet('method')) {
			$this->writeMeta($this->prepareGet('meta'));
			$data = $this->prepareGet('data');
			$this->write($this->getPathFile($this->prepareGet('id')), $data, false);


			$this->prepareReset(); // reseta array prepare
			$this->removeCacheAll(); //remover todos os caches
			return $data;
		}
		elseif ('select' === $this->prepareGet('method')) {
			$this->prepareReset(); // reseta array prepare

			$data = $this->parserAndSelect();

			$offset = $this->query['offset'];
        	$limit  = $this->query['limit'];

        	if ($limit > 0)  $data = array_slice($data, $offset, $limit, true);
        	elseif ($offset > 0) $data = array_slice($data, $offset, true);

			return $data;
		}
		elseif ('removeById' === $this->prepareGet('method')) {

			$meta = $this->getMeta();
			foreach ($this->prepareGet('id') as $id) {		
				if (!$this->fileExists($id) && !in_array($id, $meta['indexes'])) throw new Exception(sprintf('Nao foi encontrado o arquivo id::%s', $id));
				
				unset($meta['indexes'][$id]);
				unlink($this->getPathFile($id));
				$meta['length']--;
				$meta['lastId'] = end($meta['indexes']);
			}
			$this->writeMeta($meta);
			$executed = true;
		}


		if ($executed) {
			$this->prepareReset();
			$this->removeCacheAll();// remove todo os cache 
			return true;//
		}
		else return null;

	}


	/**
	 *Analizar  e Ordenar
	 * @param array &$array 
	 * @param array|null $order NULL utiliza a variavel ja setada 
	 * @return array Retorna array ordenada
	 */
	private function parserAndOrder(array &$array, array $order=null)
	{

		if(is_null($order)) $order = $this->query['order'];
		// ordernar padrao ===
        if ('desc' == $order['type'] && 'id' == $order['by']) krsort($array); //decrescente
        elseif ('asc' == $order['type'] && 'id' == $order['by']) ksort($array); // crescente

        elseif ('asc' == $order['type']) {
        	$func = function($a, $b) use($order) {
        		$a = $a[$order['by']];
        		$b = $b[$order['by']];
        		if ($a == $b) return 0;
        		return ($a < $b) ? -1 : 1; //asc
        		// return ($a > $b) ? -1 : 1; //desc
        	};
        	uasort($array, $func);
        }
        elseif ('desc' == $order['type']) {
        	$func = function($a, $b) use($order) {
        		$a = $a[$order['by']];
        		$b = $b[$order['by']];
        		if ($a == $b) return 0;
        		// return ($a < $b) ? -1 : 1; //asc
        		return ($a > $b) ? -1 : 1; //desc
        	};
        	uasort($array, $func);
        }

        return $array;
	}

	private function parserAndSelect()
	{
		if (!isset($this->query['table'])) throw new Exception('Nao ha tabela para consulta');
		
        $order  	= $this->query['order'];
        $where  	= $this->query['where'];
        $select 	= $this->query['select'];

        $cacheName =  sha1(json_encode($order + (array)$where + (array)$select));
        // BEGIN caso exista cache ===
        if ($this->hasCache($cacheName)) {
        	$result = $this->readCache($cacheName);
        	return $result;
        }
        //END cao exista cache =======
        if (!$this->hasMeta()) throw new Exception('Nao ha arquivo metadata para consulta');

        $meta = $this->getMeta();
        $indexes = $meta['indexes'];

        if (empty($meta['indexes'])) return null;//

        $result = [];//init result
        // $data = [];// init data
        //condicao where ====
        if (empty($where)) {
        	foreach ($indexes as $id) {
        		$data = $this->read($this->getBaseNameFile($id));
        		$result[$id] = empty($select) ? $data : $this->parserAndFilter($data, $select);
        	}
        	

        } else {
        	if (isset($where['id'])) {
        		$indexes = (array)$where['id'];
        		unset($where['id']);	
        	}
        	foreach ($indexes as $id) {
    			$data = $this->read($this->getBaseNameFile($id));
    			if ($this->parserWhere($data, $where)) $result[$id] = empty($select) ? $data : $this->parserAndFilter($data, $select);
    		}
        }

        $this->parserAndOrder($result, $order);//ordenar
        $this->writeCache($cacheName, $result);//salvar cache
        // configurar limite e o inicio (deslocar)
        return $result;
	}

	/**
	 * Saber se existe as condicoes 
	 * @param array $array  Array base
	 * @param array $wheres Condicoes
	 * @return bool TRUE caso as todas as condicoes existirem
	 */
	private function parserWhere(array $array, array $wheres)
	{

		foreach ($variable as $key => $value) {
			if (isset($array[$key]) && in_array($value, (array)$array[$key])) continue;
			return false;
		}
		return true;
	}
	/**
	 * Analizar e filtrar. Criara nova array com os elementos selecionados
	 * @param array $array Array base
	 * @param array $selectors Seletores
	 * @return array Nova Array
	 */
	private function parserAndFilter(array $array, array $selectors)
	{
		$newArr = [];
		foreach ($selectors as $key) {
			if (isset($array[$key])) $newArr[] = $array[$key]; 
		}
		return $newArr;
	}
	


	/**
	 * Ordernar
	 * @param string $by Chave/Valor para comparacao
	 * @param string $type apenas DESC ou ASC
	 * @return this
	 */
 	public function order($by='id', $type='desc')
	{

		$this->query['order']['by'] = $by;
		$this->query['order']['type'] = $type;
		return $this;
	}


	/**
	 * Limite para consulta
	 * @param number $n Posição limite para consulta
	 * @return this
	 */
	public function limit($n)
	{
		$this->query['limit']  = $n;
		return $this;
	}
	/**
	 * Começar a partir de
	 * @param number $n posição onde tem que começar a leitura
	 * @return this
	 */
	public function offset($n)
	{
		$this->query['offset'] = $n;
		return $this;
	}
	
	/**
	 * configurar Condições  / Filtro
	 * @param array $array ARRAY da condições
	 * @return this
	 */
	public function where(array $array)
	{
		$this->query['where'] = $array;
		return $this;
	}

	/**
	 * Description
	 * @return number Quantidade
	 */
	public function length()
	{
		return $this->getMeta('length');
	}


	/**
	 * Gerar um simples hash
	 * @param string $string 
	 * @return string
	 */
	private function simplesHash($string)
	{
		// return hash('crc32', $string); //lower
		// return strtolower(str_replace('=', '', base64_encode($string))); //lower
		// return is_numeric($string) ? $string/.5 . '.' . $string+$string; //fast
		// return  str_pad($string, 24, 'a0b1c2d3e4f5g6900');//alternative
		if (is_numeric($string)) return '' . ($string+1)/3.14159265359; //number PI
		else return $string[2] . $string[0] . $string[1] . $string[0];
	}
	/**
	 * Saber se tem o cache
	 * @param mixed $name 
	 * @return mixed
	 */
	private function hasCache($name)
	{
		return file_exists($this->getPathCache($name)); 
	}
	/**
	 * Escrever(salvar) o cache
	 * @param mixed $name Nome do cache
	 * @param mixed $content Conteudo a ser salvo
	 * @return bool
	 */
	private function writeCache($name, $content)
	{
		return $this->write($this->getPathCache($name), $content, false);	
	}
	/**
	 * Ler o cache
	 * @param mixed $name 
	 * @return mixed
	 */
	private function readCache($name)
	{
		return $this->read($this->getPathCache($name), false);
	}
	/**
	 * Remover todos os caches
	 * @return bool
	 */
	private function removeCacheAll()
	{
		return $this->directoryInstance->delete($this->getPathCache(), false);
	}
	/**
	 * Obter o caminho do cache
	 * @param mixed $name NULL retorna apenas o diretorio
	 * @return string Caminho completo do cache
	 */
	private function getPathCache($name=null)
	{
		$path = $this->query['tablePath'] . $this->cacheNameDir . '/';
		if (is_null($name)) return $path;
		return $path . $name . '.php';
	}
	/**
	 * Gerar o caminho do arquivo
	 * @param type $id ID
	 * @param bool $addHash Adicionar ou nao HASH ao nome do arquivo
	 * @return string retorna a string caminho montada
	 */
	private function getPathFile($id)
	{
		return $this->query['tablePath'] . $this->getBaseNameFile($id);
	}
	/**
	 * obter o nome base do arquivo
	 * @param mixed $id ID
	 * @param bool $addHash Adicionar ou nao HASH ao nome do arquivo 
	 * @return string
	 */
	private function getBaseNameFile($id)
	{
		return $this->simplesHash($id)  . 'i' . $id . '.php';
	}
	/**
	 * Saber se arquivo existe
	 * @param type $nameFile Nome do Arquivo
	 * @return bool
	 */
	private function fileExists($nameFile)
	{
		return file_exists($this->getPathFile($nameFile));
	}
	/**
	 * Ler o arquivo
	 * @param string $pathOrFile  caminho ou nome do arquivo : file.php
	 * @param bool $relative Setar $path como relativo
	 * @return mixed
	 */
	private function read($pathOrFile, $relative = true)
	{
		if ($relative) $pathOrFile = $this->query['tablePath'] . $pathOrFile;

		if (!($contents = file_get_contents($pathOrFile))) throw new Exception(sprintf('Nao foi possivel ler o arquivo: "%s"', $pathOrFile));
		 ;
		$contents = substr($contents, $this->strlenDenyAccess);
		return json_decode($contents, true);
	}

	/**
	 * Description
	 * @param string $pathOrFile caminho do arquivo ou nome do arquivo
	 * @param array $array array a ser salvo
	 * @param bool $relative  setar $path como relativo
	 * @return type
	 */
	private function write($pathOrFile,  $content, $relative = true)
	{
		if ($relative) $pathOrFile = $this->query['tablePath'] . $pathOrFile;

		if (is_array($content)) $content = json_encode($content);
		// if (is_array($content)) $content = json_encode($content, JSON_FORCE_OBJECT);

		return is_numeric(file_put_contents($pathOrFile, $this->strDenyAccess . $content  , LOCK_EX));
	}

	/**
	 * Coletar infor do metaData
	 * @param bool $outJSON TRUE: retorna o string JSON
	 * @return string|array
	 */
	public function meta($outJSON=false)
	{
		return ($outJSON) ? json_encode($this->getMeta()) : $this->getMeta();
	}
	/**
	 * Gerar informacoes da tabela 
	 * @param string|null $key Chave a retornar: lastId, length, indexes
	 * @return array
	 */
	private function getMeta($key=null)
	{
		/**
		 * [lastId]
		 * [legth]
		 * [indexes]
		 */
		if (!isset($this->query['table'])) throw new Exception('Não existe tabela para consultar');
		if (!$this->hasMeta()) return false;

		$data= $this->read($this->metaBaseName);
		return (!is_null($key)) ? $data[$key] : $data;
	}

	/**
	 * Saber se tem metaData
	 * @return bool
	 */
	private function hasMeta()
	{
		if (!isset($this->query['table'])) throw new Exception('Não existe tabela para consultar');
		return file_exists($this->query['tablePath'] . $this->metaBaseName);
	}

	/**
	 * Salvar o conteudo metaData
	 * @param type $array array do conteudo a ser salvo
	 * @return bool
	 */
	private function writeMeta($array)
	{
		if (!isset($this->query['table'])) throw new Exception('Não existe tabela para consultar');
		return $this->write($this->metaBaseName, $array);
	}

	/**
	 * Auxiliar para a variavel $prepare, SETA
	 * @param string $method 
	 * @param array|null $data 
	 * @param number $id 
	 * @param array|null $meta 
	 * @return void
	 */
	private function prepareSet($method, $data=null, $id=0, $meta=null)
	{
		$this->prepare['method'] = $method;
		$this->prepare['data'] 	 = $data;
		$this->prepare['meta']   = $meta;
		$this->prepare['id']     = $id;
	}
	/**
	 * Auxiliar para a variavel $prepare, RESETA. deixa todos os valores em NULL
	 * @return void
	 */
	private function prepareReset()
	{
		$this->prepareSET(null, null, null, null);
	}
	/**
	 * Auxiliar para a variavel $prepare, OBTEM
	 * @param mixed $key Nome da chave para retornar. validos: method, data, meta, id
	 * @return array Retorna a variavel (array) $prepare
	 */
	private function prepareGet($key)
	{
		// if(!isset($this->prepare[$key])) throw new Exception(sprintf('Nao existe chave "%s" em (array) $prepare.', $key));
		return $this->prepare[$key];
	}

}// END class