<?php

/**************************************************************************************************
** SmartXML_node class **
*
* Used during iteration of a SmartXML. Allows interface to the node's properties.
* There's no public constructor, used only from iterator itself.
**************************************************************************************************/

class SmartXML_node {

		//------------------------------------------------------------------------------------------### properties
		protected $core;
		protected $iterator;

		public $tagName;
		public $position;
		public $path;
		public $depth;


		//--------------------------------------------------------------------------------------------# __construct
		protected function __construct($iterator,$node,$iterated=false)
		{
			$this->iterator = $iterator;
			$this->core     = !isset($node->tagName) ? null : $node;

			$this->tagName  = $this->core ? $iterator->getTagName($this->core) : null;
			$this->position = $iterated ? 1+$iterator->entId : null;
			$this->path     = $iterated ? $iterator->path : null;
			$this->depth    = $iterated ? count($this->path) : null;
		}
		public static function create_SmartXML_node($iterator,$node,$iterated=false)
		{
			return new SmartXML_node($iterator,$node,$iterated);
		}
		public static function falseNode($iterator)
		{
			return new SmartXML_node($iterator,null);
		}


		//--------------------------------------------------------------------------------------------# _getCore
		public function _getCore()
		{
			return $this->core;
		}


		//--------------------------------------------------------------------------------------------# _getIterator
		public function _getIterator()
		{
			return $this->iterator;
		}



		//--------------------------------------------------------------------------------------------# __toString
		public function __toString()
		{
			return $this->iterator->xmlRoot->saveXML($this->core);
		}


		//--------------------------------------------------------------------------------------------# __isset
		public function __isset($name)
		{
			if($this->core && ($name=="tagName" || $name=="position" || $name=="path" || $name=="depth"))
				return 1;


			if($name=="parentNode" || $name=="previousSibling" || $name=="nextSibling" || $name=="firstChild" || $name=="lastChild" )
				return 6;

			if($name=="childNodes" || $name=="subTree")
				return 7;

			if($name=="xpath")
				return 8;

			if($name=="attributes")
				return 9;


			if(isset($this->core->$name))
				return 2;

			return isset($this->iterator->$name) ? 3 : 0;
		}


		//--------------------------------------------------------------------------------------------# __get
		public function __get($name)
		{
			if($name=="nodeValue" && !is_null($this->iterator->encoding))
			{
				return iconv("UTF-8", $this->iterator->encoding, $this->core->nodeValue);
			}
			if(is_null($this->position) && $name=="position")
				$this->position = $this->getPosition();

			if(is_null($this->path) && ($name=="xpath" || $name=="path" || $name=="depth"))
			{
				$this->getPath();
			}

			switch($this->__isset($name))
			{
				case 0 : return null;
				case 1 : return $this->$name;
				case 2 : return $this->core->$name;
				case 3 : return $this->iterator->$name;

				case 6 : return new SmartXML_node($this->iterator,$this->core->$name);
				case 7 : return new SmartXML_nodeList_iterator($this->iterator,$this->core->childNodes);
				case 8 : return ($this->depth ? "/" : "").implode("/",$this->path)."/".$this->tagName;
				case 9 : return new SmartXML_node_attributes($this);
			}
		}


		//--------------------------------------------------------------------------------------------# __set
		public function __set($name, $value)
		{
			if($name=="nodeValue")
			{
				return $this->iterator->setValue($value,$this->core);
			}
		}


		//--------------------------------------------------------------------------------------------# hasChild
		public function hasChildNodes()
		{
			return $this->core->hasChildNodes();
		}


		//--------------------------------------------------------------------------------------------# insertNode
		public function insertNode($name,$value=null,$after=null)
		{
			if(!strlen($name) || !$this->core) return;

			if(!is_null($this->iterator->encoding))
			{
				$name  = iconv($this->iterator->encoding,"UTF-8", $name);
				$value = is_null($value) ? null : iconv($this->iterator->encoding,"UTF-8", $value);
			}

			if(!is_null($value))
				$newNode = $this->iterator->xmlRoot->createElement($name,$value);
			else
				$newNode = $this->iterator->xmlRoot->createElement($name);

			if($newNode)
			{
				if(!is_null($after))
					$this->core->insertBefore($newNode,$after->core);
				else
					$this->core->appendChild($newNode);
			}

			return new SmartXML_node($this->iterator,$newNode);
		}


		//--------------------------------------------------------------------------------------------# removeChild
		public function removeChild(SmartXML_node $child)
		{
			return $this->core->removeChild($child->core);
		}



		//--------------------------------------------------------------------------------------------# _die
		public function _die()
		{
			if($this->core && $this->core->parentNode)
			{
				// iteration safe seppuku
				if($this->iterator->node->isSameNode($this->core) && $this->iterator->valid())
				{
					$this->iterator->next();
					$this->iterator->keepNext();
				}
				$this->core->parentNode->removeChild($this->core);
			}
		}


		//--------------------------------------------------------------------------------------------# getElementsByTagName
		public function getElementsByTagName($tag)
		{
			if(!is_null($this->iterator->encoding))
				$tag = iconv($this->iterator->encoding,"UTF-8", $tag);

			return new SmartXML_nodeList_iterator($this->iterator,$this->core->getElementsByTagName($tag));
		}


		//--------------------------------------------------------------------------------------------# importNode
		public function importNode($node,$deep=false)
		{
			if(!$node->core) return self::FalseNode($this->iterator);

			$newNode = $this->iterator->xmlRoot->importNode($node->core,$deep);

			if($newNode)
			{
				return new SmartXML_node($this->iterator,$this->core->appendChild($newNode));
			}

			return self::FalseNode($this->iterator);
		}


		//--------------------------------------------------------------------------------------------# isEqual
		public function isEqual($other)
		{
			if($other->tagName != $this->tagName || $other->nodeValue != $this->nodeValue)
				return false;

			foreach($other->attributes as $name=>$value)
				if($this->attributes->$name != $value)
					return false;

			foreach($this->attributes as $name=>$value)
				if($other->attributes->$name != $value)
					return false;

			return true;
		}


		//--------------------------------------------------------------------------------------------# getPosition
		protected function getPosition()
		{
			$parent = $this->core ? $this->core->parentNode : null;

			if($parent && isset($parent->tagName))
			{
				$brothers = $parent->getElementsByTagName($this->core->tagName);
				for($i=0; $i < $brothers->length; $i++)
				{
					if($brothers->item($i)->isSameNode($this->core))
						return 1+$i;
				}
			}

			return 0;
		}



		//--------------------------------------------------------------------------------------------# getPath
		protected function getPath()
		{
			$this->path = array();
			$path=array();
			$go = $this->core ? $this->core->parentNode : null;

			while($go && isset($go->tagName))
			{
				$path[]=$this->iterator->getTagName($go);
				$go=$go->parentNode;
			}

			$path = array_reverse($path);
			$i=0;
			foreach($path as $tag)
				$this->path[$i++]=$tag;

			$this->depth = count($this->path);

			return $this->path;
		}



		//--------------------------------------------------------------------------------------------# xpath
		public function xpath($query)
		{
			return $this->iterator->xpath->evaluate($query,$this);
		}

}





/**************************************************************************************************
** SmartXML_node_attributes class **
*
* Provides an interface to the attributes of a SmartXML_node object.
**************************************************************************************************/
class SmartXML_node_attributes implements Iterator {

		protected $node;
		protected $XML_node;
		protected $pos = 0;


		//--------------------------------------------------------------------------------------------# __construct
		public function __construct($node)
		{
			$this->XML_node = $node;
			$this->node     = $node->_getCore();
		}

		//--------------------------------------------------------------------------------------------# __isset
		public function __isset($name)
		{
			return $this->node->hasAttribute($name);
		}


		//--------------------------------------------------------------------------------------------# __isset
		public function __unset($name)
		{
			return $this->node->removeAttribute($name);
		}


		//--------------------------------------------------------------------------------------------# __get
		public function __get($name)
		{
			$encoding = 0;
			if(!is_null($this->XML_node->_getIterator()->encoding))
			{
				$name  = iconv($this->XML_node->_getIterator()->encoding,"UTF-8",$name);
				$encoding = $this->XML_node->_getIterator()->encoding;
			}
			return $encoding ? iconv("UTF-8",$this->XML_node->_getIterator()->encoding,$this->node->getAttribute($name)) : $this->node->getAttribute($name);
		}


		//--------------------------------------------------------------------------------------------# __set
		public function __set($name, $value)
		{
			if(!is_null($this->XML_node->_getIterator()->encoding))
			{
				$name  = iconv($this->XML_node->_getIterator()->encoding,"UTF-8",$name);
				$value = iconv($this->XML_node->_getIterator()->encoding,"UTF-8",$value);
			}
			return $this->node->setAttribute($name,$value);
		}


		//--------------------------------------------------------------------------------------------# __toString
		public function __toString()
		{
			$str="";
			for($i=0;$i < $this->node->attributes->length;$i++)
				$str.=" ".$this->node->attributes->item($i)->name."=\"".$this->node->attributes->item($i)->value."\"";

			return ltrim($str);
		}


		//--------------------------------------------------------------------------------------------# count
		public function count()
		{
			return $this->node->attributes->length;
		}


		//--------------------------------------------------------------------------------------------# as_array
		public function as_array()
		{
			$arrAttributes=array();
			for($i=0;$i < $this->node->attributes->length;$i++)
			{
				$arrAttributes[$this->node->attributes->item($i)->name]=$this->node->attributes->item($i)->value;
			}

			return $arrAttributes;
		}


		//--------------------------------------------------------------------------------------------# rewind
		public function rewind()
		{
			$this->pos=0;
		}

		//--------------------------------------------------------------------------------------------# rewind
		public function current()
		{
			return $this->node->attributes->item($this->pos)->value;
		}

		//--------------------------------------------------------------------------------------------# key
		public function key()
		{
			return $this->node->attributes->item($this->pos)->name;
		}

		//--------------------------------------------------------------------------------------------# next
		public function next()
		{
			$this->pos++;
		}

		//--------------------------------------------------------------------------------------------# valid
		public function valid()
		{
			return $this->pos < $this->node->attributes->length;
		}
}






/**************************************************************************************************
** SmartXML class **
*
* Just for your comfort. Knows what you need, just as you need it.
**************************************************************************************************/
class SmartXML implements Iterator {

		//------------------------------------------------------------------------------------------### properties
		public $xmlRoot;
		public $node;
		public $depth;
		public $path;
		public $entId;
		public $xpath;

		public $keepNextStep = 0;

		public $encoding;
		protected $level;


		//--------------------------------------------------------------------------------------------# __construct
		public function __construct($xml=null,$encoding=null,$formatted=true)
		{
			$this->xmlRoot   = new DOMDocument("1.0",is_null($encoding) ? "UTF-8" : $encoding);
			$this->path      = array();
			$this->depth     = 0;
			$this->entId     = 0;
			$this->level     = array();
			$this->encoding  = $encoding;

			if(!is_null($xml))
				$this->init($xml);

			$this->xmlRoot->formatOutput=$formatted;
			$this->xpath = new SmartXML_xpath_interface($this);

		}



		//--------------------------------------------------------------------------------------------# init
		public function init($xml=null)
		{
			if($xml)
			{
				switch(is_object($xml) ? get_class($xml) : "text")
				{
					case "SimpleXMLElement" : $this->xmlRoot->loadXML($xml->asXML());break;
					case "DOMDocument"      : $this->xmlRoot = $xml;break;

					default : 
						$xml = trim(preg_replace("/([\\n\\s]*)(<\/?)([^>]*>)/","\\2\\3",$xml));
						if(!is_null($this->encoding))
						{
							// the character encoding in the xml header is necessary for non-utf8 xml sources
							$headerEnd = strpos($xml,"?>");
							$xml = substr($xml,$headerEnd===false ? 0 : 2+$headerEnd);
							$xml = "<?xml version=\"1.0\" encoding=\"".$this->encoding."\"?>".$xml;
						}
						$this->xmlRoot->loadXML($xml);

						if(is_null($this->encoding) && $this->xmlRoot->encoding!="UTF-8")
							$this->encoding = $this->xmlRoot->encoding;
				}
			}
			else
			{
				$this->path      = array();
				$this->level     = array();
				$this->depth     = 0;
			}
			$this->node = $this->xmlRoot->documentElement;
		}


		//--------------------------------------------------------------------------------------------# getTagName
		public function getTagName($node)
		{
			return is_null($this->encoding) ? $node->tagName : iconv("UTF-8", $this->encoding, $node->tagName);
		}



		//--------------------------------------------------------------------------------------------# createRoot
		public function createRoot($name)
		{
			$name = is_null($this->encoding) ? $name : iconv($this->encoding,"UTF-8", $name);
			return SmartXML_node::create_SmartXML_node($this,$this->xmlRoot->appendChild($this->xmlRoot->createElement($name)));
		}


		//--------------------------------------------------------------------------------------------# getRoot
		public function getRoot()
		{
			return SmartXML_node::create_SmartXML_node($this,$this->xmlRoot->documentElement);
		}


		//--------------------------------------------------------------------------------------------# insertFromXpath
		public function insertFromXpath($xpath,$value=null)
		{
			$path = explode("/",$xpath);

			if($this->getNode($xpath) || $this->xmlRoot->documentElement && $path[0]!=$this->getTagName($this->xmlRoot->documentElement))
				return SmartXML_node::falseNode($this);

			$node = $this->getCore();

			foreach($path as $node)
			{
				//;
			}
			$node = $this->getNode($xpath);

		}




		//--------------------------------------------------------------------------------------------# rewind
		public function rewind()
		{
			$this->path      = array();
			$this->depth     = 0;
			$this->node      = $this->xmlRoot->documentElement;
			$this->level     = array();

			$this->keepNextStep = 0;

			$this->evaluateNode();
		}

		//--------------------------------------------------------------------------------------------# current
		public function current()
		{
			return SmartXML_node::create_SmartXML_node($this,$this->node,true);
		}

		//--------------------------------------------------------------------------------------------# key
		public function key()
		{
			return $this->getTagName($this->node);
		}


		//--------------------------------------------------------------------------------------------# keepNext
		public function keepNext()
		{
			$this->keepNextStep = 1;
		}


		//--------------------------------------------------------------------------------------------# next
		public function next()
		{
			if($this->keepNextStep)
			{
				$this->keepNextStep=0;
				return;
			}

			if($this->node)
				$this->path[$this->depth]=$this->getTagName($this->node);
			
			if($this->node->firstChild && get_class($this->node->firstChild)!="DOMText")
			{
				$this->node=$this->node->firstChild;

				$this->level[$this->depth] = array();

				$this->depth++;
			}
			else if($this->node->nextSibling)
			{
				$this->node=$this->node->nextSibling;
			}
			else
			{
				while($this->node)
				{
					$this->node=$this->node->parentNode;
					$this->depth--;
					if($this->node && $this->node->nextSibling)
					{
						$this->node=$this->node->nextSibling;
						break;
					}
				}
			}

			$this->evaluateNode();
		}


		//--------------------------------------------------------------------------------------------# evaluateNode
		protected function evaluateNode()
		{
			if($this->node)
			{
				if(isset($this->level[$this->depth-1][$this->node->tagName]))
					$this->level[$this->depth-1][$this->node->tagName]++;
				else
					$this->level[$this->depth-1][$this->node->tagName]=0;

				$this->path = array_slice($this->path, 0, $this->depth);
				$this->entId = $this->level[$this->depth-1][$this->node->tagName];
			}
		}

		//--------------------------------------------------------------------------------------------# valid
		public function valid()
		{
			return $this->node;
		}




		//--------------------------------------------------------------------------------------------# setValue
		public function setValue($value,$node=null)
		{
			if(is_null($node))
				$node = $this->node;

			if($node->firstChild && get_class($node->firstChild)!="DOMText")
				return false;

			else if($node->firstChild)
			{
				$node->removeChild($node->firstChild);
			}

			if(!is_null($this->encoding))
				$value = iconv($this->encoding, "UTF-8", $value);

			$node->appendChild(new DOMText($value));

			return true;
		}



		//--------------------------------------------------------------------------------------------# saveXML
		public function saveXML()
		{
			return $this->xmlRoot->saveXML();
		}



		//--------------------------------------------------------------------------------------------# formattedXML
		public function formattedXML()
		{
			$formatted = $this->xmlRoot->formatOutput;

			$this->xmlRoot->formatOutput = true;
			$xml = $this->xmlRoot->saveXML();

			$this->xmlRoot->formatOutput=$formatted;

			return $xml;
		}



		//--------------------------------------------------------------------------------------------# __toString
		public function __toString()
		{
			return $this->xmlRoot->saveXML();
		}


		//--------------------------------------------------------------------------------------------# hasNode
		public function hasNode($xpath)
		{
			return $this->xpath->evaluate("count(".$xpath.")");
		}


		//--------------------------------------------------------------------------------------------# getNode
		public function getNode($xpath)
		{
			return $this->xpath->query($xpath)->first();
		}


		//--------------------------------------------------------------------------------------------# isEqual
		public function isEqual(SmartXML $other)
		{
			$state = $this->saveState();
			$this->rewind();
			foreach($other as $node)
			{
				if(!$this->valid() || !$node->isEqual($this->current()))
				{
					$this->restoreState($state);
					return false;
				}

				$this->next();
			}
			$this->restoreState($state);
			return !$this->valid();
		}


		//--------------------------------------------------------------------------------------------# isSameStructure
		public function isSameStructure(SmartXML $other)
		{
			$state = $this->saveState();
			$this->rewind();
			foreach($other as $node)
			{
				if(!$this->valid() || $node->tagName!=$this->node->tagName)
				{
					$this->restoreState($state);
					return false;
				}

				$this->next();
			}
			$this->restoreState($state);
			return !$this->valid();
		}


		//--------------------------------------------------------------------------------------------# count
		public function count()
		{
			return $this->xpath->evaluate("count(//*)");
		}


		//--------------------------------------------------------------------------------------------# saveState
		protected function saveState()
		{
			$state = array();

			$state["node"]      = $this->node;
			$state["depth"]     = $this->depth;
			$state["path"]      = $this->path;
			$state["entId"]     = $this->entId;
			$state["level"]     = $this->level;

			return $state;
		}


		//--------------------------------------------------------------------------------------------# saveState
		protected function restoreState($state)
		{
			$this->node      = $state["node"];
			$this->depth     = $state["depth"];
			$this->path      = $state["path"];
			$this->entId     = $state["entId"];
			$this->level     = $state["level"];
		}
	}





/**************************************************************************************************
** SmartXML_xpath_interface class **
*
* Made for XPATH evaluations.
**************************************************************************************************/

	class SmartXML_xpath_interface {

		protected $parent;
		protected $xpath;

		//--------------------------------------------------------------------------------------------# __construct
		public function __construct(SmartXML $parent)
		{
			$this->parent = $parent;
			$this->xpath  = new DOMXPath($parent->xmlRoot);
		}

		//--------------------------------------------------------------------------------------------# query
		public function query($query)
		{
			if(!is_null($this->parent->encoding))
				$query = iconv($this->parent->encoding, "UTF-8", $query);

			return new SmartXML_nodeList_iterator($this->parent,$this->xpath->query($query));
		}


		//--------------------------------------------------------------------------------------------# evaluate
		public function evaluate($query,$node=null)
		{
			if(!is_null($this->parent->encoding))
				$query = iconv($this->parent->encoding, "UTF-8", $query);

			if(is_null($node))
				$res = $this->xpath->evaluate($query);
			else
				$res = $this->xpath->evaluate($query,$node->_getCore());

			if(is_object($res) && get_class($res)=="DOMNodeList")
				return new SmartXML_nodeList_iterator($this->parent,$res);
			else
				return $res;
		}

	}





/**************************************************************************************************
** SmartXML_nodeList_iterator class **
*
* Made for iterating through XPATH evaluations.
**************************************************************************************************/
	class SmartXML_nodeList_iterator extends SmartXML {

		protected $nodeList;
		protected $nodePosition;

		//--------------------------------------------------------------------------------------------# __construct
		public function __construct($parent,$nodeList)
		{
			parent::__construct($parent->xmlRoot);

			$this->nodeList     = $nodeList;
			$this->nodePosition = 0;

			$this->rewind();
			$this->encoding = $parent->encoding;
		}


		//--------------------------------------------------------------------------------------------# saveAsXML
		public function saveAsXML()
		{
			foreach($this as $node)
				;
		}

		//--------------------------------------------------------------------------------------------# first
		public function first()
		{
			return SmartXML_node::create_SmartXML_node($this,$this->nodeList->item(0));
		}


		//--------------------------------------------------------------------------------------------# count
		public function count()
		{
			return $this->nodeList->length;
		}


		//--------------------------------------------------------------------------------------------# count
		public function item($index)
		{
			return $index<$this->nodeList->length ? SmartXML_node::create_SmartXML_node($this,$this->nodeList->item($index)) : SmartXML_node::falseNode($this);
		}





		//--------------------------------------------------------------------------------------------# rewind
		public function rewind()
		{
			parent::rewind();
			$this->nodePosition=0;
			$this->node = $this->nodeList->item(0);
		}

		//--------------------------------------------------------------------------------------------# current
		public function current()
		{
			return SmartXML_node::create_SmartXML_node($this,$this->node);
		}


		//--------------------------------------------------------------------------------------------# next
		public function next()
		{
			if($this->keepNextStep)
			{
				$this->keepNextStep=0;
				return;
			}

			$this->node = $this->nodeList->item(++$this->nodePosition);
		}

		//--------------------------------------------------------------------------------------------# valid
		public function valid()
		{
			return $this->nodePosition < $this->nodeList->length;
		}

	}
?>