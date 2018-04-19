<?php

declare(strict_types = 1);

namespace OpenCalais;

/**
 * Class OpenCalais
 *
 * @author Nicolas Duteil <nduteil@gmail.com>
 * @package OpenCalais
 * @see http://www.opencalais.com/wp-content/uploads/folder/ThomsonReutersOpenCalaisAPIUserGuideR11_6.pdf
 */
class OpenCalais
{

    /** default values */
    const DEFAULT_INPUT_CONTENT_CLASS   = 'news' ;
    const DEFAULT_INPUT_CONTENT_TYPE    = 'text/raw';
    const DEFAULT_OUTPUT_FORMAT         = 'application/json';
    const DEFAULT_DOCUMENT_LANGUAGE     = 'English';
    const DEFAULT_DOCUMENT_CHARSET      = 'utf-8';
    const DEFAULT_OMIT_ORGINAL_DOCUMENT = true;

    /** @var string OpenCalais REST API Url */
    private $apiRestUrl = 'https://api.thomsonreuters.com/permid/calais';
   
    /** @var string OpenCalais REST API token (your token) */
    private $apiToken;

    /** @var array document class ("to optimize extraction") */
    private $supportedInputContentClass = array(
        'news',        // news stories
        'research',    // research reports
    );

    /** @var array supported document formats */
    private $supportedInputContentType = array(
        'text/html',        // Use this value when submitting web pages
        'text/xml',         // Use this value when submitting XML content
        'text/raw',         // Use this value when submitting clean, unformatted text
        'application/pdf'   // Use this value when submitting PDF files as binary streams
    );

    /** @var array supported output formats */
    private $supportedOutputFormat = array(
        'xml/rdf',
        'application/json',
        'text/n3'
    );

    /** @var array supported document language */
    private $supportedDocumentLanguage = array(
        'English',
        'French',
        'Spanish'
    );

    /** @var array output tags; default: none (all tags) */
    private $supportedOutputTags = array(
        'additionalcontactdetails',
        'company',
        'country',
        'deal',
        'company',
        'industry',
        'person',
        'socialtags',
        'topic'
    );

    /** @var string selected input content class */
    private $inputContentClass;

    /** @var string selected input content type */
    private $inputContentType;

    /** @var string selected output format */
    private $outputFormat;

    /** @var array selected output tags */
    private $outputTags;

    /** @var bool Omit original document in output response or not */
    private $outputOmitOriginalDocument;
    
    /** @var string Curl Resource handler */
    private $curlHandler;

    /** @var string httpError (for API http error code management) */
    private $httpError;

    /** @var string sha256 last document signature */
    private $lastDocumentSignature;

    /** @var mixed last successful API response */
    private $lastAPIResponse;

    /** @var array extracted topics */
    private $topics;
    /** @var array extracted social tags */
    private $socialTags;
    /** @var array extracted entities */
    private $entities;

    /**
     * Constructor
     * @param string $apiToken          Your API token
     * @param string $inputContentType  (optional) Indicates the input content type (mime type)
     * @param string $outputFormat      (optional) Defines the output response format (mime type)
     * @throws InvalidArgumentException
     */
    public function __construct($apiToken, $inputContentType = self::DEFAULT_INPUT_CONTENT_TYPE, $outputFormat = self::DEFAULT_OUTPUT_FORMAT)
    {
        if (empty($apiToken)) {
            throw new \InvalidArgumentException('An OpenCalais API token is required to use this class');
        }
        $this->apiToken = $apiToken;

        $this->setInputContentType($inputContentType);
        $this->setOutputFormat($outputFormat);
        $this->setInputContentClass(self::DEFAULT_INPUT_CONTENT_CLASS);
        $this->setOutputOmitOriginalDocument(self::DEFAULT_OMIT_ORGINAL_DOCUMENT);
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        if (null !== $this->curlHandler) {
            curl_close($this->curlHandler);
        }
    }

    /**
     * Set input content class
     * @param string Indicates the input content class
     * @throws InvalidArgumentException
     */
    public function setInputContentClass($inputContentClass)
    {
        if (! in_array($inputContentClass, $this->supportedInputContentClass)) {
            throw new \InvalidArgumentException('Unsupported input class (' . $inputContentClass . ')'); 
        }
        $this->inputContentClass = $inputContentClass;
    }

    /**
     * Set input content type
     * @param string Indicates the input content type (mime type)
     * @throws InvalidArgumentException
     */
    public function setInputContentType($inputContentType)
    {
        if (! in_array($inputContentType, $this->supportedInputContentType)) {
            throw new \InvalidArgumentException('Unsupported input mime type (' . $inputContentType . ')'); 
        }
        $this->inputContentType = $inputContentType;
    }

    /**
     * Set output format
     * @param string Defines the output response format (mime type)
     * @throws InvalidArgumentException
     */
    public function setOutputFormat($outputFormat)
    {
        if (! in_array($outputFormat, $this->supportedOutputFormat)) {
            throw new \InvalidArgumentException('Unsupported output format (' . $outputFormat . ')');
        }
        $this->outputFormat = $outputFormat;
    }

    /**
     * Defines if original document is omitted in output response
     * true recommended for large document
     * @param boolean
     * @throws InvalidArgumentException
     */
    public function setOutputOmitOriginalDocument($bool)
    {
        if (! is_bool($bool)) {
            throw new \InvalidArgumentException('Not a boolean (' . $bool . ')');
        }
        $this->outputOmitOriginalDocument = $bool;
    }

    /**
     * Set output tags (filter output response content)
     * @param array Defines the output response content
     * @throws InvalidArgumentException
     */
    public function setOutputTags($tags)
    {
        if (! is_array($tags)) {
            throw new \InvalidArgumentException('Not an array (' . $tags . ')');
        }
        // reset previous selection
        $this->outputTags = null;
        // add tags
        foreach ($tags as $tag) {
            // skip non supported tags without throwing exception
            if (in_array($tag, $this->supportedOutputTags)) {
                $this->outputTags[] = $tag;
            }
            // but give user a warning
            else {
                trigger_error('Unsupported output tag (' . $tag .')', E_USER_WARNING);
            }
        }
    }

    /**
     * Return raw API response for document
     * @param string $document
     * @param string $documentLanguage (optional)
     * @param string $documentCharset (optional)
     * @return mixed
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function queryAPI($document, $documentLanguage = self::DEFAULT_DOCUMENT_LANGUAGE, $documentCharset = self::DEFAULT_DOCUMENT_CHARSET, $forceQuery = false) {

        // Return stored datas if a previous API call has been done for this document
        if ($this->verifyHash($document) && !$forceQuery) {
            return $this->lastAPIResponse;
        }

        // reset potential previous error
        $this->httpError = null;

        // check language
        if (! in_array($documentLanguage, $this->supportedDocumentLanguage)) {
            throw new \InvalidArgumentException('Unsupported document language (' . $documentLanguage . ')');
        }

        $headers = array(
            'X-AG-Access-Token: ' . $this->apiToken,
            'Accept-Charset: ' . $documentCharset,
            'Content-Type: ' . $this->inputContentType . '; charset=' . $documentCharset,
            'outputFormat: ' . $this->outputFormat,
            'x-calais-language:' . $documentLanguage,
            'x-calais-contentClass: ' . $this->inputContentClass,
            'omitOutputtingOriginalText: ' . $this->outputOmitOriginalDocument,
        );

        if (is_array($this->outputTags) && count($this->outputTags)) {
            $headers[] = 'x-calais-selectiveTags: '. implode(', ', $this->outputTags);
        }

        $this->curlHandler = curl_init();
        curl_setopt($this->curlHandler, CURLOPT_URL, $this->apiRestUrl);
        curl_setopt($this->curlHandler, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->curlHandler, CURLOPT_HEADER, 0);
        curl_setopt($this->curlHandler, CURLOPT_POST, 1);
        curl_setopt($this->curlHandler, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->curlHandler, CURLOPT_HEADERFUNCTION, $this->checkHeaders());
        curl_setopt($this->curlHandler, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($this->curlHandler, CURLOPT_POSTFIELDS, $document);

        $response = curl_exec($this->curlHandler);

        if ($response) {
            if (! empty($this->httpError)) {
                // error comes json formated whatever the requested output format is
                $response = json_decode($response);
                if (isset($response->fault)) {
                    throw new \Exception('API error:' . $response->fault->faultstring);
                }
            }
            else {
                $this->lastAPIResponse = $response;
                return $response;
            }
        }
        else {
            throw new \Exception('Curl error: ' . curl_error($this->curlHandler));
        }
    }

    /**
     * Check Curl query HTTP response headers for error code
     */
    private function checkHeaders()
    {
        return function ($ch, $header) {
            if (!empty(trim($header))) {
                // OC API returns 4xx/5xx errors
                if (preg_match('#^HTTP.*\s(4\d{2}|5\d{2})\s#i', $header)) {
                    $this->httpError = $header;
                }
            } 
            return strlen($header);
        };
    }

    /**
     * Return Entities for document
     * @see getItem for description
     * @return array
     */
    public function getEntities($document, $documentLanguage = self::DEFAULT_DOCUMENT_LANGUAGE, $documentCharset = self::DEFAULT_DOCUMENT_CHARSET)
    {
        return $this->getItem('entities', $document, $documentLanguage, $documentCharset);
    }

    /**
     * Return Topics for document
     * @see getItem for description
     * @return array
     */
    public function getTopics($document = null, $documentLanguage = self::DEFAULT_DOCUMENT_LANGUAGE, $documentCharset = self::DEFAULT_DOCUMENT_CHARSET)
    {
        return $this->getItem('topics', $document, $documentLanguage, $documentCharset);
    }

    /**
     * Return socialTags for document
     * @see getItem for description
     * @return array
     */
    public function getSocialTags($document = null, $documentLanguage = self::DEFAULT_DOCUMENT_LANGUAGE, $documentCharset = self::DEFAULT_DOCUMENT_CHARSET)
    {
        return $this->getItem('socialTags', $document, $documentLanguage, $documentCharset);
    }

    /**
     * Call extractDatas that reset output settings to application/json and update lastAPIResponse var
     * @param string property name
     * @param string $document (optional) But must be given for the first call
     * @param string $documentLanguage (optional)
     * @param string $documentCharset (optional)
     */
    private function getItem($item, $document = null, $documentLanguage = self::DEFAULT_DOCUMENT_LANGUAGE, $documentCharset = self::DEFAULT_DOCUMENT_CHARSET)
    {
        if ($document) {
            $this->extractDatas($document, $documentLanguage, $documentLanguage);
        }
        
        return $this->$item;
    }

    /**
     * Extract datas for document and fill properties
     * Reset output settings to application/json and update lastAPIResponse var
     * @param string $document
     * @param string $documentLanguage (optional)
     * @param string $documentCharset (optional)
     * @return object
     * @throws Exception
     */
    public function extractDatas($document, $documentLanguage = self::DEFAULT_DOCUMENT_LANGUAGE, $documentCharset = self::DEFAULT_DOCUMENT_CHARSET)
    {
        // Force outputFormat if necessary
        if ('application/json' == $this->outputFormat) {
            $forceQuery = false;
        }
        else {
            $this->outputFormat = 'application/json';
            $forceQuery = true;
            // Give user a warning
            trigger_error('Reseting outputFormat to application/json', E_USER_WARNING);
        }
        
        $response = $this->queryAPI($document, $documentLanguage, $documentLanguage, $forceQuery);

        if ($response) {
            $object = json_decode($response);
            if (is_object($object)) {
                foreach ($object as $key => $data) {
                    if (isset($data->_typeGroup)) {
                        switch ($data->_typeGroup) {
                            case 'topics':
                                $this->topics[$data->name] = array(
                                    'id' => $key,
                                    'score' => $data->score,
                                );
                                break;
                            case 'socialTag':
                                $this->socialTags[$data->name] = array(
                                    'id' => $key,
                                    'importance' => $data->importance,
                                    'originalValue' => $data->originalValue,
                                );
                                break;
                            case 'entities':
                                $this->entities[$data->_type][$data->name] = array(
                                    'id' => $key,
                                    'commonName' => isset($data->commonname) ? $data->commonname : '',
                                    'relevance' => $data->relevance,
                                    'instances' => array(),
                                );
                                // fill instances part
                                foreach ($data->instances as $instance) {
                                    $this->entities[$data->_type][$data->name]['instances'][] = array(
                                        'detection' => $instance->detection,
                                        'exact' => $instance->exact,
                                        'offset' => $instance->offset,
                                        'prefix' => $instance->prefix,
                                        'suffix' => $instance->suffix
                                    );
                                }
                                if (isset($data->confidence)) {
                                    foreach ($data->confidence as $k => $v) {
                                        $this->entities[$data->_type][$data->name]['confidence'][$k] = $v;
                                    }
                                }
                                break;
                        }
                    }
                }
            }
            else {
                throw new \Exception('Not an object (' . gettype($object) . ')');
            }
        }
        else {
            throw new \Exception('No returned data');
        }
    }

    /**
     * Create / check hash for document
     */
    function verifyHash($document)
    {
        $hash = hash('sha256', $document);
        if ($hash != $this->lastDocumentSignature) {
            $this->lastDocumentSignature = $hash;
            return false;
        }
        else {
            return true;
        }
    }

    /**
     * Return last successful API response
     * @return mixed
     */
    public function getLastAPIResponse()
    {
        return $this->lastAPIResponse;
    }

    /**
     * Magic getter
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
    }

    /**
     * Magic setter (to avoid misuse as new property can be added)
     */
    public function __set($name, $value) {
        throw new \Exception('Prohibited operation');
    }
}