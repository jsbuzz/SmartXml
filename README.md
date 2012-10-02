SmartXML - Library for managing and manipulating XML documents
==============================================================



## example

	$srcXml = new SmartXML(file_get_contents("test.xml"),"ISO-8859-2");
	$dstXml = new SmartXML('',"ISO-8859-2");

	$nodes = $dstXml->createRoot('nodes');

	foreach($xml->xpath->query("//Node") as $node)
	{
		echo $node->xpath("./title")->first()->nodeValue;
		$newNode = $nodes->insertNode("Node");

		foreach($node->childNodes as $tag=>$value)
			$newNode->insertNode($tag,$value);
		
		foreach($node->attributes as $key=>$value)
			$newNode->attributes->__set($key,$value);
	}