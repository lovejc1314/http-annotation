<?php


namespace EasySwoole\HttpAnnotation\Utility;


use EasySwoole\HttpAnnotation\Annotation\MethodAnnotation;
use EasySwoole\HttpAnnotation\Annotation\ObjectAnnotation;
use EasySwoole\HttpAnnotation\Annotation\ParserInterface;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiDescription;
use EasySwoole\HttpAnnotation\AnnotationTag\ApiGroupAuth;
use EasySwoole\HttpAnnotation\AnnotationTag\Param;
use EasySwoole\HttpAnnotation\Exception\Exception;
use EasySwoole\Validate\Error;

class AnnotationDoc
{
    private $scanner;
    private $CLRF = "\n\n";

    function __construct(?ParserInterface $parser = null)
    {
        $this->scanner = new Scanner($parser);
    }

    function scan2Html(string $dirOrFile,?string $extMd = null)
    {
        $info = $this->scan2Markdown($dirOrFile);
        if(empty($info)){
            throw new Exception('none doc info');
        }else{
            $md = $info['markdown'];
            $md = "{$extMd}{$this->CLRF}{$md}";
            return str_replace('{$rawMd}',$md,file_get_contents(__DIR__ . "/docPage.tpl"));
        }
    }

    function scan2Markdown(string $dirOrFile):array
    {
        $groupList = [];
        $list = $this->scanner->scanAnnotations($dirOrFile);
        $markdown = '';
        /** @var ObjectAnnotation $objectAnnotation */
        foreach ($list as $objectAnnotation)
        {
            if($objectAnnotation->getApiGroupTag()){
                $currentGroupName = $objectAnnotation->getApiGroupTag()->groupName;
            }else{
                $currentGroupName = 'default';
            }
            //第一次构建分全局信息
            if(!isset($groupList[$currentGroupName])){
                $groupList[$currentGroupName] = [];
                $markdown .= "<h1 class='group-title' id='{$currentGroupName}'>{$currentGroupName}</h1>{$this->CLRF}";
                $groupDescTag = $objectAnnotation->getApiGroupDescriptionTag();
                if($groupDescTag){
                    $markdown .= "<h3 class='group-description'>全局描述</h3>{$this->CLRF}";
                    $description = $this->parseDescTagContent($groupDescTag);
                    $markdown .= $description."{$this->CLRF}";
                }


                $onRequest = $objectAnnotation->getMethod('onRequest');
                $groupAuthTagList = $objectAnnotation->getGroupAuthTag();
                $paramTags = $objectAnnotation->getParamTag();
                if($onRequest instanceof MethodAnnotation){
                    $groupAuthTagList = array_merge($groupAuthTagList,$onRequest->getApiAuth());
                    $paramTags = array_merge($paramTags,$onRequest->getParamTag());
                }

                if(!empty($groupAuthTagList)){
                    $markdown .= "<h3 class='group-auth'>全局权限说明</h3>{$this->CLRF}";
                    $markdown .= $this->buildParamMarkdown($groupAuthTagList);
                }

                if(!empty($paramTags)){
                    $markdown .= "<h3 class='group-param'>全局参数说明</h3>{$this->CLRF}";
                    $markdown .= $this->buildParamMarkdown($paramTags);
                }

                // $markdown .= "<hr class='group-hr'/>{$this->CLRF}";
            }

            //遍历全部方法
            /**
             * @var  $methodName
             * @var MethodAnnotation $method
             */
            foreach ($objectAnnotation->getMethod() as $methodName => $method)
            {
                //仅仅渲染有api标记的方法
                $apiTag = $method->getApiTag();
                if($apiTag){
                    $groupList[$currentGroupName] = $method->getMethodName();
                    $deprecated = '';
                    if($apiTag->deprecated){
                        $deprecated .= "<sup class='deprecated'>已废弃</sup>";
                    }
                    $markdown .= "<h2 class='api-method {$currentGroupName}' id='{$currentGroupName}-{$methodName}'>{$apiTag->name}{$deprecated}</h2>{$this->CLRF}";

                    $markdown .= "<h3 class='method-description'>基本信息</h3>{$this->CLRF}";
                    //兼容api指定
                    if($method->getApiDescriptionTag()){
                        $description = $this->parseDescTagContent($method->getApiDescriptionTag());
                    }else if(!empty($apiTag->description)){
                        trigger_error('@Api tag description property is deprecated,use @ApiDescription tag instead',E_USER_DEPRECATED);
                        $description = $apiTag->description;
                    }else{
                        $description = '暂无描述';
                    }

                    // 请求路径
                    $markdown .= "<p><strong>Path：</strong> {$apiTag->path}</p>{$this->CLRF}";

                    // 请求方法
                    $allow = $method->getMethodTag();
                    if($allow){
                        $allow = implode(",",$allow->allow);
                    }else{
                        $allow = '不限制';
                    }
                    $markdown .= "<p><strong>Method：</strong> {$allow}</p>{$this->CLRF}";

                    // 接口描述
                    $markdown .= "<p><strong>接口描述：</strong> {$description}</p>{$this->CLRF}";


                    $authParams = $method->getApiAuth();
                    if (!empty($authParams)) {
                        $markdown .= "<h3 class='auth-params'>权限字段</h3> {$this->CLRF}";
                        $markdown .= $this->buildParamMarkdown($authParams);
                    }

                    $requestParams = $method->getParamTag();
                    if (!empty($requestParams)) {
                        $markdown .= "<h3 class='request-params'>请求字段</h3> {$this->CLRF}";
                        $markdown .= $this->buildParamMarkdown($requestParams);
                    }

                    if(!empty($method->getApiRequestExample())){
                        $markdown .= "<h3 class='request-example'>请求示例</h3> {$this->CLRF}";
                        $index = 1;
                        foreach ($method->getApiRequestExample() as $example){
                            $example = $this->parseDescTagContent($example);
                            if(!empty($example)){
                                $markdown .= "<p><strong>请求示例{$index}</strong></p>{$this->CLRF}";
                                $markdown .= "```\n{$example}\n```{$this->CLRF}";
                                $index++;
                            }
                        }
                    }

                    $markdown .= "<h3 class='response-part'>响应</h3>{$this->CLRF}";
                    $params = $method->getApiSuccessParam();
                    if (!empty($params)) {
                        $markdown .= "<h4 class='response-params'>成功响应字段</h4> {$this->CLRF}";
                        $markdown .= $this->buildParamMarkdown($params);
                    }
                    if(!empty($method->getApiSuccess())){
                        $markdown .= "<h4 class='api-success-example'>成功响应示例</h4> {$this->CLRF}";
                        $index = 1;
                        foreach ($method->getApiSuccess() as $example){
                            $example = $this->parseDescTagContent($example);
                            if(!empty($example)){
                                $markdown .= "<p><strong>成功响应示例{$index}</strong></p>{$this->CLRF}";
                                $markdown .= "```\n{$example}\n```{$this->CLRF}";
                                $index++;
                            }
                        }
                    }
                    $params = $method->getApiFailParam();
                    if (!empty($params)) {
                        $markdown .= "<h4 class='response-params'>失败响应字段</h4> {$this->CLRF}";
                        $markdown .= $this->buildParamMarkdown($params);
                    }

                    if(!empty($method->getApiFail())){
                        $markdown .= "<h4 class='api-fail-example'>失败响应示例</h4> {$this->CLRF}";
                        $index = 1;
                        foreach ($method->getApiFail() as $example){
                            $example = $this->parseDescTagContent($example);
                            if(!empty($example)){
                                $markdown .= "<p><strong>失败响应示例{$index}</strong></p>{$this->CLRF}";
                                $markdown .= "```\n{$example}\n```{$this->CLRF}";
                                $index++;
                            }
                        }
                    }
                }
            }

        }
        return ['markdown'=>$markdown,'methodGroup'=>$groupList];
    }

    private function parseDescTagContent(?ApiDescription $apiDescription = null)
    {
        if($apiDescription == null){
            return null;
        }
        $ret = null;
        if ($apiDescription->type == 'file' && file_exists($apiDescription->value)) {
            $ret = file_get_contents($apiDescription->value);
        } else {
            $ret = $apiDescription->value;
        }
        $ret = $this->descTagContentFormat($ret);
        if(empty($ret)){
            $ret = '暂无描述';
        }
        return $ret;
    }

    private function descTagContentFormat($content)
    {
        if(is_array($content)){
            return json_encode($content,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }
        $json = json_decode($content,true);
        if($json){
            $content = json_encode($json,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        }else{
            libxml_disable_entity_loader(true);
            $xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NOERROR | LIBXML_NOCDATA);
            if($xml){
                $content = $xml->saveXML();
            }
        }
        return $content;
    }

    private function buildParamMarkdown($params)
    {
        $markdown = '';
        if (!empty($params)) {

            // 判断是否属于 ApiGroupAuth
            $firstParams = $params[array_keys($params)[0]];
            $isApiGroupAuth = false;
            if ($firstParams instanceof ApiGroupAuth) {
                $isApiGroupAuth = true;
                $markdown .= "| 字段 | 来源 | 类型 | 默认值 | 描述 | 验证规则 | 忽略Action |\n";
                $markdown .= "| ---- | ---- | ---- | ---- | ---- | ---- | ---- |\n";
            } else {
                $markdown .= "| 字段 | 来源 | 类型 | 默认值 | 描述 | 验证规则 |\n";
                $markdown .= "| ---- | ---- | ---- | ---- | ---- | ---- |\n";
            }

            /** @var Param $param */
            foreach ($params as $param) {
                // 类型
                if(!empty($param->type)){
                    $type = $param->type;
                }else{
                    $type = '默认';
                }

                // 来源
                if(!empty($param->from)){
                    $from = implode(",",$param->from);
                }else{
                    $from = "不限";
                }

                // 默认值
                if($param->defaultValue !== null){
                    $defaultValue = $param->defaultValue;
                }else{
                    $defaultValue = '-';
                }

                // 描述
                if(!empty($param->description)){
                    $description = $param->description;
                }else{
                    $description = '-';
                }

                // 验证规则
                if(empty($param->validateRuleList)){
                    $rule = '-';
                }else{
                    $rule = '';
                    foreach ($param->validateRuleList as $ruleName => $conf){
                        $arrayCheckFunc = ['inArray', 'notInArray', 'allowFile', 'allowFileType'];
                        if (in_array($ruleName, $arrayCheckFunc)) {
                            if(!is_array($conf[0])){
                                $conf = [$conf];
                            }
                        }
                        $err = new Error($param->name,null,null,$ruleName,null,$conf);
                        $temp = $err->__toString();
                        $temp = "{$ruleName}: ".substr($temp,strlen($param->name));
                        $rule .= $temp." </br>";
                    }
                }

                //忽略action
                if ($isApiGroupAuth) {
                    $ingoreAction = implode(',',$param->ignoreAction);
                    if(empty($ingoreAction)){
                        $ingoreAction = '-';
                    }
                    $markdown .= "| {$param->name} |  {$from}  | {$type} | {$defaultValue} | {$description} | {$rule} | {$ingoreAction} |\n";
                } else {
                    $markdown .= "| {$param->name} |  {$from}  | {$type} | {$defaultValue} | {$description} | {$rule} |\n";
                }

            }
            $markdown .= "\n\n";
        }
        return $markdown;
    }
}
