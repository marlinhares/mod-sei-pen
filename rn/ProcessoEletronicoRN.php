<?php
class ProcessoEletronicoRN extends InfraRN {

    //const PEN_WEBSERVICE_LOCATION = 'https://desenv-api-pen.intra.planejamento/interoperabilidade/soap/v1_1/';
    
  /* TAREFAS DE EXPEDI��O DE PROCESSOS */
  //Est� definindo o comportamento para a tarefa $TI_PROCESSO_EM_PROCESSAMENTO
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_EXPEDIDO = 501;
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_RECEBIDO = 502;
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_RETRANSMITIDO = 503;
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_TRAMITE_CANCELADO = 504;
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_TRAMITE_RECUSADO = 505;
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_TRAMITE_EXTERNO = 506;
  public static $TI_PROCESSO_ELETRONICO_PROCESSO_ABORTADO = 507;

  /* TAREFAS DE EXPEDI��O DE DOCUMENTOS */
  //Est� definindo o comportamento para a tarefa $TI_PROCESSO_BLOQUEADO
  public static $TI_PROCESSO_ELETRONICO_DOCUMENTO_EXPEDIDO = 506;
  public static $TI_PROCESSO_ELETRONICO_DOCUMENTO_RECEBIDO = 507;
  public static $TI_PROCESSO_ELETRONICO_DOCUMENTO_RETRANSMITIDO = 508;
  public static $TI_PROCESSO_ELETRONICO_DOCUMENTO_TRAMITE_CANCELADO = 509;
  public static $TI_PROCESSO_ELETRONICO_DOCUMENTO_TRAMITE_RECUSADO = 510;

  /* N�VEL DE SIGILO DE PROCESSOS E DOCUMENTOS */
  public static $STA_SIGILO_PUBLICO = '1';
  public static $STA_SIGILO_RESTRITO = '2';
  public static $STA_SIGILO_SIGILOSO = '3';

  /* RELA��O DE SITUA��ES POSS�VEIS EM UM TR�MITE */    
  public static $STA_SITUACAO_TRAMITE_INICIADO = 1;                           // Iniciado - Metadados recebidos pela solu��o        
  public static $STA_SITUACAO_TRAMITE_COMPONENTES_ENVIADOS_REMETENTE = 2;     // Componentes digitais recebidos pela solu��o        
  public static $STA_SITUACAO_TRAMITE_METADADOS_RECEBIDO_DESTINATARIO = 3;    // Metadados recebidos pelo destinat�rio
  public static $STA_SITUACAO_TRAMITE_COMPONENTES_RECEBIDOS_DESTINATARIO = 4; // Componentes digitais recebidos pelo destinat�rio
  public static $STA_SITUACAO_TRAMITE_RECIBO_ENVIADO_DESTINATARIO = 5;        // Recibo de conclus�o do tr�mite enviado pelo destinat�rio do processo
  public static $STA_SITUACAO_TRAMITE_RECIBO_RECEBIDO_REMETENTE = 6;          // Recibo de conclus�o do tr�mite recebido pelo remetente do processo
  public static $STA_SITUACAO_TRAMITE_CANCELADO = 7;                          // Tr�mite do processo ou documento cancelado pelo usu�rio (Qualquer situa��o diferente de 5 e 6)     
  public static $STA_SITUACAO_TRAMITE_RECUSADO = 9;                           // Tr�mite do processo recusado pelo destinat�rio (Situa��es 2, 3, 4)

  /* OPERA��ES DO HIST�RICO DO PROCESSO */
  // 02 a 18 est�o registrados na tabela rel_tarefa_operacao
  public static $OP_OPERACAO_REGISTRO = "01";



  const ALGORITMO_HASH_DOCUMENTO = 'SHA256';
  
  /**
   * Motivo para recusar de tramite de componente digital pelo formato
   */
  const MTV_RCSR_TRAM_CD_FORMATO = '01';
  /**
   * Motivo para recusar de tramite de componente digital que esta corrompido
   */
  const MTV_RCSR_TRAM_CD_CORROMPIDO = '02';
  /**
   * Motivo para recusar de tramite de componente digital que n�o foi enviado
   */
  const MTV_RCSR_TRAM_CD_FALTA = '03';
  /**
   * Motivo para recusar de tramite de componente digital
   */
  const MTV_RCSR_TRAM_CD_OUTROU = '99';
    
  public static $MOTIVOS_RECUSA = array(
      "01"  => "Formato de componente digital n�o suportado",
      "02" => "Componente digital corrompido",
      "03" => "Falta de componentes digitais",
      "99" => "Outro"
  );
    

  private $strWSDL = null;
  private $objPenWs = null;
  private $options = null;

  public function __construct() {
    $objInfraParametro = new InfraParametro(BancoSEI::getInstance());

    $strEnderecoWebService = $objInfraParametro->getValor('PEN_ENDERECO_WEBSERVICE');
    $strLocalizacaoCertificadoDigital = $objInfraParametro->getValor('PEN_LOCALIZACAO_CERTIFICADO_DIGITAL');
    $strSenhaCertificadoDigital = $objInfraParametro->getValor('PEN_SENHA_CERTIFICADO_DIGITAL');

    if (InfraString::isBolVazia($strEnderecoWebService)) {
      throw new InfraException('Endere�o do servi�o de integra��o do Processo Eletr�nico Nacional (PEN) n�o informado.');
    }

    if (!@file_get_contents($strLocalizacaoCertificadoDigital)) {
      throw new InfraException("Certificado digital de autentica��o do servi�o de integra��o do Processo Eletr�nico Nacional(PEN) n�o encontrado.");
    }

        //TODO: Urgente - Remover senha do certificado de autentica��o dos servi�os do PEN da tabela de par�metros
    if (InfraString::isBolVazia($strSenhaCertificadoDigital)) {
      throw new InfraException('Dados de autentica��o do servi�o de integra��o do Processo Eletr�nico Nacional(PEN) n�o informados.');
    }

    $this->strWSDL = $strEnderecoWebService . '?wsdl';
    $this->strComumXSD = $strEnderecoWebService . '?xsd=comum.xsd';
    $this->strLocalCert = $strLocalizacaoCertificadoDigital;
    $this->strLocalCertPassword = $strSenhaCertificadoDigital;

    $this->options = array(
      'soap_version' => SOAP_1_1
      , 'local_cert' => $this->strLocalCert
      , 'passphrase' => $this->strLocalCertPassword
      , 'resolve_wsdl_remote_includes' => false
      , 'trace' => true
      , 'encoding' => 'UTF-8'
      , 'attachment_type' => BeSimple\SoapCommon\Helper::ATTACHMENTS_TYPE_MTOM
      , 'ssl' => array(
        'allow_self_signed' => true
        )
      );
  }

  protected function inicializarObjInfraIBanco()
  {
    return BancoSEI::getInstance();
  }
  
    /**
     * Verifica se o uma url esta ativa
     * 
     * @param string $strUrl url a ser testada
     * @param string $strLocalCert local f�sico do certificado .pem
     * @throws InfraException
     * @return null
     */
    private function testaUrl($strUrl = '', $strLocalCert = ''){
        
        $arrParseUrl = parse_url($this->strWSDL);
        // � melhor a p�gina inicial que todo o arquivo wsdl
        $strUrl = $arrParseUrl['scheme'].'://'.$arrParseUrl['host'];

        $strCommand = sprintf('curl %s --insecure --cert %s 2>&1', $strUrl, $this->options['local_cert']);
        $numRetorno = 0;
        $arrOutput = array();

        @exec($strCommand, $arrOutput, $numRetorno);

        if($numRetorno > 0){

            throw new InfraException('Falha de comunica��o com o Barramento de Servi�os. Por favor, tente novamente mais tarde.', $e);
        }
    }
  
  private function getObjPenWs() {
      
    if($this->objPenWs == null) { 
      $this->testaUrl($this->strWSDL, $this->options['local_cert']);
      try {
        
        $objConfig = ConfiguracaoSEI::getInstance();
          
        if($objConfig->isSetValor('SEI', 'LogPenWs')){
            
            $this->objPenWs = new LogPenWs($objConfig->getValor('SEI', 'LogPenWs'), $this->strWSDL, $this->options);
        }
        else {
            
            $this->objPenWs = new BeSimple\SoapClient\SoapClient($this->strWSDL, $this->options);
        }
      } catch (Exception $e) {
        throw new InfraException('Erro acessando servi�o.', $e);
      }
    }

    return $this->objPenWs;
  }

    //TODO: Avaliar otimiza��o de tal servi�o para buscar individualmente os dados do reposit�rio de estruturas
  public function consultarRepositoriosDeEstruturas($numIdentificacaoDoRepositorioDeEstruturas) {

    $objRepositorioDTO = null;

    try{
      $parametros = new stdClass();
      $parametros->filtroDeConsultaDeRepositoriosDeEstrutura = new stdClass();
      $parametros->filtroDeConsultaDeRepositoriosDeEstrutura->ativos = false;

      $result = $this->getObjPenWs()->consultarRepositoriosDeEstruturas($parametros);

      if(isset($result->repositoriosEncontrados->repositorio)){

        if(!is_array($result->repositoriosEncontrados->repositorio)) {
          $result->repositoriosEncontrados->repositorio = array($result->repositoriosEncontrados->repositorio);    
        }

        foreach ($result->repositoriosEncontrados->repositorio as $repositorio) {
          if($repositorio->id == $numIdentificacaoDoRepositorioDeEstruturas){
            $objRepositorioDTO = new RepositorioDTO();
            $objRepositorioDTO->setNumId($repositorio->id);
            $objRepositorioDTO->setStrNome(utf8_decode($repositorio->nome));
            $objRepositorioDTO->setBolAtivo($repositorio->ativo);
          }
        }
      }
    } catch(Exception $e){
      throw new InfraException("Erro durante obten��o dos reposit�rios", $e);                        
    }

    return $objRepositorioDTO;
  }

  public function listarRepositoriosDeEstruturas() {

    $arrObjRepositorioDTO = array();

    try{
      $parametros = new stdClass();
      $parametros->filtroDeConsultaDeRepositoriosDeEstrutura = new stdClass();
      $parametros->filtroDeConsultaDeRepositoriosDeEstrutura->ativos = true;

      $result = $this->getObjPenWs()->consultarRepositoriosDeEstruturas($parametros);

      if(isset($result->repositoriosEncontrados->repositorio)){

        if(!is_array($result->repositoriosEncontrados->repositorio)) {
          $result->repositoriosEncontrados->repositorio = array($result->repositoriosEncontrados->repositorio);    
        }

        foreach ($result->repositoriosEncontrados->repositorio as $repositorio) {
          $item = new RepositorioDTO();
          $item->setNumId($repositorio->id);
          $item->setStrNome(utf8_decode($repositorio->nome));
          $item->setBolAtivo($repositorio->ativo);
          $arrObjRepositorioDTO[] = $item;
        }
      }
    } catch(Exception $e){
      throw new InfraException("Erro durante obten��o dos reposit�rios", $e);                        
    }

    return $arrObjRepositorioDTO;
  }

  public function consultarEstrutura($idRepositorioEstrutura, $numeroDeIdentificacaoDaEstrutura, $bolRetornoRaw = false) {

        try {
            $parametros = new stdClass();
            $parametros->filtroDeEstruturas = new stdClass();
            $parametros->filtroDeEstruturas->identificacaoDoRepositorioDeEstruturas = $idRepositorioEstrutura;
            $parametros->filtroDeEstruturas->numeroDeIdentificacaoDaEstrutura = $numeroDeIdentificacaoDaEstrutura;
            $parametros->filtroDeEstruturas->apenasAtivas = false;

            $result = $this->getObjPenWs()->consultarEstruturas($parametros);

            if ($result->estruturasEncontradas->totalDeRegistros == 1) {

                $arrObjEstrutura = is_array($result->estruturasEncontradas->estrutura) ? $result->estruturasEncontradas->estrutura : array($result->estruturasEncontradas->estrutura);
                $objEstrutura = current($arrObjEstrutura);
                
                $objEstrutura->nome = utf8_decode($objEstrutura->nome);
                $objEstrutura->sigla = utf8_decode($objEstrutura->sigla);
                
                if ($bolRetornoRaw !== false) {

                    if (isset($objEstrutura->hierarquia) && isset($objEstrutura->hierarquia->nivel)) {

                        $objEstrutura->hierarquia->nivel = (array) $objEstrutura->hierarquia->nivel;

                        foreach ($objEstrutura->hierarquia->nivel as &$objNivel) {

                            $objNivel->nome = utf8_decode($objNivel->nome);
                        }
                    }
                    return $objEstrutura;
                } 
                else {

                    $objEstruturaDTO = new EstruturaDTO();
                    $objEstruturaDTO->setNumNumeroDeIdentificacaoDaEstrutura($objEstrutura->numeroDeIdentificacaoDaEstrutura);
                    $objEstruturaDTO->setStrNome($objEstrutura->nome);
                    $objEstruturaDTO->setStrSigla($objEstrutura->sigla);
                    $objEstruturaDTO->setBolAtivo($objEstrutura->ativo);
                    $objEstruturaDTO->setBolAptoParaReceberTramites($objEstrutura->aptoParaReceberTramites);
                    $objEstruturaDTO->setStrCodigoNoOrgaoEntidade($objEstrutura->codigoNoOrgaoEntidade);
                    return $objEstruturaDTO;
                }
            }
        } 
        catch (Exception $e) {
            throw new InfraException("Erro durante obten��o das unidades", $e);
        }
    }

    public function listarEstruturas($idRepositorioEstrutura, $nome='') 
  {
    $arrObjEstruturaDTO = array();

    try{
      $idRepositorioEstrutura = filter_var($idRepositorioEstrutura, FILTER_SANITIZE_NUMBER_INT);
      if(!$idRepositorioEstrutura) {
        throw new InfraException("Reposit�rio de Estruturas inv�lido");                
      }

      $parametros = new stdClass();
      $parametros->filtroDeEstruturas = new stdClass();
      $parametros->filtroDeEstruturas->identificacaoDoRepositorioDeEstruturas = $idRepositorioEstrutura;
            //$parametros->filtroDeEstruturas->numeroDeIdentificacaoDaEstrutura = 218794;//$numeroDeIdentificacaoDaEstrutura;
      $parametros->filtroDeEstruturas->nome = utf8_encode($nome);
      $parametros->filtroDeEstruturas->apenasAtivas = false;

      $result = $this->getObjPenWs()->consultarEstruturas($parametros);

      if($result->estruturasEncontradas->totalDeRegistros > 0) {

        if(!is_array($result->estruturasEncontradas->estrutura)) {
          $result->estruturasEncontradas->estrutura = array($result->estruturasEncontradas->estrutura);    
        }

        foreach ($result->estruturasEncontradas->estrutura as $estrutura) {
          $item = new EstruturaDTO();
          $item->setNumNumeroDeIdentificacaoDaEstrutura($estrutura->numeroDeIdentificacaoDaEstrutura);
          $item->setStrNome(utf8_decode($estrutura->nome));
          $item->setStrSigla(utf8_decode($estrutura->sigla));
          $item->setBolAtivo($estrutura->ativo);
          $item->setBolAptoParaReceberTramites($estrutura->aptoParaReceberTramites);
          $item->setStrCodigoNoOrgaoEntidade($estrutura->codigoNoOrgaoEntidade);
          
            if(!empty($estrutura->hierarquia->nivel)) {
                
                $array = array();
                
                foreach($estrutura->hierarquia->nivel as $nivel) {
                    
                    $array[] = utf8_decode($nivel->sigla);
                }
                
                $item->setArrHierarquia($array);
            }
          
          $arrObjEstruturaDTO[] = $item;
        }
      }            

    } catch (Exception $e) {
      throw new InfraException("Erro durante obten��o das unidades", $e);            
    }

    return $arrObjEstruturaDTO;
  }

  public function consultarMotivosUrgencia()
  {
    $curl = curl_init($this->strComumXSD);
    curl_setopt($curl, CURLOPT_URL, $this->strComumXSD);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSLCERT, $this->strLocalCert);
    curl_setopt($curl, CURLOPT_SSLCERTPASSWD, $this->strLocalCertPassword);
    $output = curl_exec($curl);
    curl_close($curl);

    $dom = new DOMDocument;
    $dom->loadXML($output);

    $xpath = new DOMXPath($dom);

    $rootNamespace = $dom->lookupNamespaceUri($dom->namespaceURI);
    $xpath->registerNamespace('x', $rootNamespace); 
    $entries = $xpath->query('/x:schema/x:simpleType[@name="motivoDaUrgencia"]/x:restriction/x:enumeration');

    $resultado = array();
    foreach ($entries as $entry) {
      $valor = $entry->getAttribute('value');

      $documentationNode = $xpath->query('x:annotation/x:documentation', $entry);
      $descricao = $documentationNode->item(0)->nodeValue;

      $resultado[$valor] = utf8_decode($descricao);
    }

    return $resultado;
  }     

  public function enviarProcesso($parametros) 
  {
    try {
            //error_log("PARAMETROS:" . print_r($parametros, true));
      return $this->getObjPenWs()->enviarProcesso($parametros);                    
    } catch (\SoapFault $fault) {
            //error_log("REQUEST:" . $this->getObjPenWs()->__getLastRequest());
            //error_log("ERROR:" . print_r($fault, true));
      $mensagem = $this->tratarFalhaWebService($fault);

            //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
            //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      error_log($mensagem);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }        
  }

  public function listarPendencias($bolTodasPendencias)
  {

    $arrObjPendenciaDTO = array();

    try {
      $parametros = new stdClass();
      $parametros->filtroDePendencias = new stdClass();
      $parametros->filtroDePendencias->todasAsPendencias = $bolTodasPendencias;    	
      $result = $this->getObjPenWs()->listarPendencias($parametros);

      if(isset($result->listaDePendencias->IDT)){

        if(!is_array($result->listaDePendencias->IDT)) {
          $result->listaDePendencias->IDT = array($result->listaDePendencias->IDT);
        }

        foreach ($result->listaDePendencias->IDT as $idt) {
          $item = new PendenciaDTO();
          $item->setNumIdentificacaoTramite($idt->_);
          $item->setStrStatus($idt->status);
          $arrObjPendenciaDTO[] = $item;
        }    			    			     		
      }
    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }

    return $arrObjPendenciaDTO;
  }       

    //TODO: Tratar cada um dos poss�veis erros gerados pelos servi�os de integra��o do PEN
  private function tratarFalhaWebService(SoapFault $fault)
  {
    error_log('$e->faultcode:' . $fault->faultcode);
    error_log('$e->detail:' . print_r($fault->detail, true));        

    $mensagem = $fault->getMessage();
    if(isset($fault->detail->interoperabilidadeException)){
      $strWsException = $fault->detail->interoperabilidadeException;

      switch ($strWsException->codigoErro) {
        case '0044':
        $mensagem = 'Processo j� possui um tr�mite em andamento';
        break;

        default:
        $mensagem = utf8_decode($fault->detail->interoperabilidadeException->mensagem);
        break;
      }
    }

    return $mensagem;
  }

  public function construirCabecalho($strNumeroRegistro = null, $idRepositorioOrigem = 0, $idUnidadeOrigem = 0, $idRepositorioDestino = 0, 
    $idUnidadeDestino = 0, $urgente = false, $motivoUrgencia = 0, $enviarTodosDocumentos = false)
  {
    $cabecalho = new stdClass();

    if(isset($strNumeroRegistro)) {
      $cabecalho->NRE = $strNumeroRegistro;            
    }

    $cabecalho->remetente = new stdClass();
    $cabecalho->remetente->identificacaoDoRepositorioDeEstruturas = $idRepositorioOrigem;
    $cabecalho->remetente->numeroDeIdentificacaoDaEstrutura = $idUnidadeOrigem;

    $cabecalho->destinatario = new stdClass();
    $cabecalho->destinatario->identificacaoDoRepositorioDeEstruturas = $idRepositorioDestino;
    $cabecalho->destinatario->numeroDeIdentificacaoDaEstrutura = $idUnidadeDestino;

    $cabecalho->urgente = $urgente;
    $cabecalho->motivoDaUrgencia = $motivoUrgencia;
    $cabecalho->obrigarEnvioDeTodosOsComponentesDigitais = $enviarTodosDocumentos;

    return $cabecalho;
  }

  public function enviarComponenteDigital($parametros)
  {
    try {
            //error_log('$this->getObjPenWs()->enviarComponenteDigital($parametros)');
            //error_log("||||||||||||||||||" . print_r($parametros, true));
      return $this->getObjPenWs()->enviarComponenteDigital($parametros);                    

    } catch (\SoapFault $fault) {
            //error_log("REQUEST:" . $this->getObjPenWs()->__getLastRequest());
            //error_log("ERROR:" . print_r($fault, true));

      $mensagem = $this->tratarFalhaWebService($fault);

            //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
            //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {

      throw new InfraException("Error Processing Request", $e);
    }        

  }


  public function solicitarMetadados($parNumIdentificacaoTramite) {

    try
    {    
      $parametros = new stdClass();
      $parametros->IDT = $parNumIdentificacaoTramite;    		
      return $this->getObjPenWs()->solicitarMetadados($parametros);
    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
      //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      error_log($mensagem);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }        
  }     

  public static function converterDataWebService($dataHoraSEI) 
  {
    $resultado = '';
    if(isset($dataHoraSEI)){
      $resultado = InfraData::getTimestamp($dataHoraSEI);
      $resultado = date(DateTime::W3C, $resultado);
    }

    return $resultado;
  }

  public static function converterDataSEI($dataHoraWebService) 
  {
    $resultado = null;
    if(isset($dataHoraWebService)){
      $resultado = strtotime($dataHoraWebService);
      $resultado = date('d/m/Y H:i:s', $resultado);
    }

    return $resultado;
  }

  public function cadastrarTramiteDeProcesso($parDblIdProcedimento, $parStrNumeroRegistro, $parNumIdentificacaoTramite, $parDthRegistroTramite, $parObjProcesso, $parNumTicketComponentesDigitais = null, $parObjComponentesDigitaisSolicitados = null)
  {
    if(!isset($parDblIdProcedimento) || $parDblIdProcedimento == 0) {
      throw new InfraException('Par�metro $parDblIdProcedimento n�o informado.');            
    }

    if(!isset($parStrNumeroRegistro)) {
      throw new InfraException('Par�metro $parStrNumeroRegistro n�o informado.');            
    }

    if(!isset($parNumIdentificacaoTramite) || $parNumIdentificacaoTramite == 0) {
      throw new InfraException('Par�metro $parStrNumeroRegistro n�o informado.');            
    }

    if(!isset($parObjProcesso)) {
      throw new InfraException('Par�metro $objProcesso n�o informado.');            
    }

    //Monta dados do processo eletr�nico
    $objProcessoEletronicoDTO = new ProcessoEletronicoDTO();
    $objProcessoEletronicoDTO->setStrNumeroRegistro($parStrNumeroRegistro);
    $objProcessoEletronicoDTO->setDblIdProcedimento($parDblIdProcedimento);

    //Montar dados dos procedimentos apensados    
    if(isset($parObjProcesso->processoApensado)){
      if(!is_array($parObjProcesso->processoApensado)){
        $parObjProcesso->processoApensado = array($parObjProcesso->processoApensado);
      }
      
      $arrObjRelProcessoEletronicoApensadoDTO = array();
      $objRelProcessoEletronicoApensadoDTO = null;
      foreach ($parObjProcesso->processoApensado as $objProcessoApensado) {
        $objRelProcessoEletronicoApensadoDTO = new RelProcessoEletronicoApensadoDTO();
        $objRelProcessoEletronicoApensadoDTO->setStrNumeroRegistro($parStrNumeroRegistro);
        $objRelProcessoEletronicoApensadoDTO->setDblIdProcedimentoApensado($objProcessoApensado->idProcedimentoSEI);
        $objRelProcessoEletronicoApensadoDTO->setStrProtocolo($objProcessoApensado->protocolo);
        $arrObjRelProcessoEletronicoApensadoDTO[] = $objRelProcessoEletronicoApensadoDTO;
      }

      $objProcessoEletronicoDTO->setArrObjRelProcessoEletronicoApensado($arrObjRelProcessoEletronicoApensadoDTO);
    }

    //Monta dados do tr�mite do processo
    $objTramiteDTO = new TramiteDTO();
    $objTramiteDTO->setStrNumeroRegistro($parStrNumeroRegistro);
    $objTramiteDTO->setNumIdTramite($parNumIdentificacaoTramite);
    $objTramiteDTO->setNumTicketEnvioComponentes($parNumTicketComponentesDigitais);
    $objTramiteDTO->setDthRegistro($this->converterDataSEI($parDthRegistroTramite));        
    $objProcessoEletronicoDTO->setArrObjTramiteDTO(array($objTramiteDTO));

    //Monta dados dos componentes digitais
    $arrObjComponenteDigitalDTO = $this->montarDadosComponenteDigital($parStrNumeroRegistro, $parNumIdentificacaoTramite, $parObjProcesso, $parObjComponentesDigitaisSolicitados);
    
    $objTramiteDTO->setArrObjComponenteDigitalDTO($arrObjComponenteDigitalDTO);
    $objProcessoEletronicoDTO = $this->cadastrarTramiteDeProcessoInterno($objProcessoEletronicoDTO);

    return $objProcessoEletronicoDTO;
  }


  //TODO: Tratar a exce��o de recebimento de um tr�mite que j� havia sido tratado no sistema
  protected function cadastrarTramiteDeProcessoInternoControlado(ProcessoEletronicoDTO $parObjProcessoEletronicoDTO) {

    if(!isset($parObjProcessoEletronicoDTO)) {
      throw new InfraException('Par�metro $parObjProcessoEletronicoDTO n�o informado.');            
    }

    $idProcedimento = $parObjProcessoEletronicoDTO->getDblIdProcedimento();
    
    //Registra os dados do processo eletr�nico
    //TODO: Revisar a forma como o barramento tratar o NRE para os processos apensados
    $objProcessoEletronicoDTOFiltro = new ProcessoEletronicoDTO();
    $objProcessoEletronicoDTOFiltro->setStrNumeroRegistro($parObjProcessoEletronicoDTO->getStrNumeroRegistro());
    $objProcessoEletronicoDTOFiltro->setDblIdProcedimento($parObjProcessoEletronicoDTO->getDblIdProcedimento());
    $objProcessoEletronicoDTOFiltro->retStrNumeroRegistro();
    $objProcessoEletronicoDTOFiltro->retDblIdProcedimento();

    $objProcessoEletronicoBD = new ProcessoEletronicoBD($this->getObjInfraIBanco());
    $objProcessoEletronicoDTO = $objProcessoEletronicoBD->consultar($objProcessoEletronicoDTOFiltro);

    if(empty($objProcessoEletronicoDTO)) {
       
        $objProcessoEletronicoDTO = $objProcessoEletronicoBD->cadastrar($objProcessoEletronicoDTOFiltro);
    }

    //Registrar processos apensados
    if($parObjProcessoEletronicoDTO->isSetArrObjRelProcessoEletronicoApensado()) {

        $objRelProcessoEletronicoApensadoBD = new RelProcessoEletronicoApensadoBD($this->getObjInfraIBanco());
        
        foreach ($parObjProcessoEletronicoDTO->getArrObjRelProcessoEletronicoApensado() as $objRelProcessoEletronicoApensadoDTOFiltro) {
        
            if($objRelProcessoEletronicoApensadoBD->contar($objRelProcessoEletronicoApensadoDTOFiltro) < 1){
                
                $objRelProcessoEletronicoApensadoBD->cadastrar($objRelProcessoEletronicoApensadoDTOFiltro); 
            }
        }
    }

        //Registrar informa��es sobre o tr�mite do processo
    $arrObjTramiteDTO = $parObjProcessoEletronicoDTO->getArrObjTramiteDTO();
    $parObjTramiteDTO = $arrObjTramiteDTO[0];     
    
    $objTramiteDTO = new TramiteDTO();
    $objTramiteDTO->retNumIdTramite();
    $objTramiteDTO->setStrNumeroRegistro($parObjTramiteDTO->getStrNumeroRegistro());
    $objTramiteDTO->setNumIdTramite($parObjTramiteDTO->getNumIdTramite());

    $objTramiteBD = new TramiteBD($this->getObjInfraIBanco());
    $objTramiteDTO = $objTramiteBD->consultar($objTramiteDTO);

    if($objTramiteDTO == null) {
      $objTramiteDTO = $objTramiteBD->cadastrar($parObjTramiteDTO);
    }        

    $objProcessoEletronicoDTO->setArrObjTramiteDTO(array($objTramiteDTO));

    //Registra informa��es sobre o componente digital do documento
    $arrObjComponenteDigitalDTO = array();
    $objComponenteDigitalBD = new ComponenteDigitalBD($this->getObjInfraIBanco());
    
    $numOrdem = 1;
    
    foreach ($parObjTramiteDTO->getArrObjComponenteDigitalDTO() as $objComponenteDigitalDTO) {
        
        $objComponenteDigitalDTOFiltro = new ComponenteDigitalDTO();
        
        $objComponenteDigitalDTOFiltro->setStrNumeroRegistro($objComponenteDigitalDTO->getStrNumeroRegistro());
        $objComponenteDigitalDTOFiltro->setDblIdProcedimento($objComponenteDigitalDTO->getDblIdProcedimento());      
        $objComponenteDigitalDTOFiltro->setDblIdDocumento($objComponenteDigitalDTO->getDblIdDocumento());    
        
         if($objComponenteDigitalBD->contar($objComponenteDigitalDTOFiltro) > 0){
             $numOrdem++;
         }
        
    }
    
    foreach ($parObjTramiteDTO->getArrObjComponenteDigitalDTO() as $objComponenteDigitalDTO) {
        
      //Verifica se o documento foi inserido pelo tr�mite atual
      if($objComponenteDigitalDTO->getDblIdDocumento() != null){
          
        $objComponenteDigitalDTO->setDblIdProcedimento($idProcedimento);
          
        $objComponenteDigitalDTOFiltro = new ComponenteDigitalDTO();
        
        $objComponenteDigitalDTOFiltro->setStrNumeroRegistro($objComponenteDigitalDTO->getStrNumeroRegistro());
        $objComponenteDigitalDTOFiltro->setDblIdProcedimento($objComponenteDigitalDTO->getDblIdProcedimento());      
        $objComponenteDigitalDTOFiltro->setDblIdDocumento($objComponenteDigitalDTO->getDblIdDocumento());            
        
        if($objComponenteDigitalBD->contar($objComponenteDigitalDTOFiltro) < 1){
            
            $objComponenteDigitalDTO->setNumOrdem($numOrdem);
            $objComponenteDigitalDTO->unSetStrDadosComplementares();
            $objComponenteDigitalDTO = $objComponenteDigitalBD->cadastrar($objComponenteDigitalDTO);
            $numOrdem++;
        }
        else {
            
            //Verifica se foi setado o envio
            if(!$objComponenteDigitalDTO->isSetStrSinEnviar()){
                $objComponenteDigitalDTO->setStrSinEnviar('N');
            }
            
            // Muda a ID do tramite e o arquivo pode ser enviado
            $objComponenteDigitalBD->alterar($objComponenteDigitalDTO);
        }
        $arrObjComponenteDigitalDTO[] = $objComponenteDigitalDTO;
      }
    }

    $objTramiteDTO->setArrObjComponenteDigitalDTO($arrObjComponenteDigitalDTO);


    //TODO: Adicionar controle de excess�o
    //...

    return $objProcessoEletronicoDTO;
  }
  
  /**
   * Retorna o hash do objecto do solicitarMetadadosResponse
   * 
   * @param object $objMeta tem que ser o componenteDigital->hash
   * @return string
   */
    public static function getHashFromMetaDados($objMeta){

        $strHashConteudo = '';
        
        if (isset($objMeta)) {
            $matches = array();
            $strHashConteudo = (isset($objMeta->enc_value)) ? $objMeta->enc_value : $objMeta->_;

            if (preg_match('/^<hash.*>(.*)<\/hash>$/', $strHashConteudo, $matches, PREG_OFFSET_CAPTURE)) {
                $strHashConteudo = $matches[1][0];
            }
        }

        return $strHashConteudo;
    }
    
  private function montarDadosComponenteDigital($parStrNumeroRegistro, $parNumIdentificacaoTramite, $parObjProcesso, $parObjComponentesDigitaisSolicitados) 
  {
    //Monta dados dos componentes digitais
    $arrObjComponenteDigitalDTO = array();
    if(!is_array($parObjProcesso->documento)) {
      $parObjProcesso->documento = array($parObjProcesso->documento);
    }
    
    foreach ($parObjProcesso->documento as $objDocumento) {            
      $objComponenteDigitalDTO = new ComponenteDigitalDTO();
      $objComponenteDigitalDTO->setStrNumeroRegistro($parStrNumeroRegistro);
      $objComponenteDigitalDTO->setDblIdProcedimento($parObjProcesso->idProcedimentoSEI); //TODO: Error utilizar idProcedimentoSEI devido processos apensados
      $objComponenteDigitalDTO->setDblIdDocumento($objDocumento->idDocumentoSEI);
      $objComponenteDigitalDTO->setNumOrdem($objDocumento->ordem);
      $objComponenteDigitalDTO->setNumIdTramite($parNumIdentificacaoTramite);
      $objComponenteDigitalDTO->setStrProtocolo($parObjProcesso->protocolo);

      //Por enquanto, considera que o documento possui apenas um componente digital
      if(is_array($objDocumento->componenteDigital) && count($objDocumento->componenteDigital) != 1) {
        throw new InfraException("Erro processando componentes digitais do processo " . $parObjProcesso->protocolo . "\n Somente � permitido o recebimento de documentos com apenas um Componente Digital.");                
      }

      $objComponenteDigital = $objDocumento->componenteDigital;
      $objComponenteDigitalDTO->setStrNome($objComponenteDigital->nome);
           
      $strHashConteudo = static::getHashFromMetaDados($objComponenteDigital->hash);

      $objComponenteDigitalDTO->setStrHashConteudo($strHashConteudo);
      $objComponenteDigitalDTO->setStrAlgoritmoHash(self::ALGORITMO_HASH_DOCUMENTO);
      $objComponenteDigitalDTO->setStrTipoConteudo($objComponenteDigital->tipoDeConteudo);
      $objComponenteDigitalDTO->setStrMimeType($objComponenteDigital->mimeType);
      $objComponenteDigitalDTO->setStrDadosComplementares($objComponenteDigital->dadosComplementaresDoTipoDeArquivo);

      //Registrar componente digital necessita ser enviado pelo tr�mite espef�fico      //TODO: Teste $parObjComponentesDigitaisSolicitados aqui       
      if(isset($parObjComponentesDigitaisSolicitados)){
        $arrObjItensSolicitados = is_array($parObjComponentesDigitaisSolicitados->processo) ? $parObjComponentesDigitaisSolicitados->processo : array($parObjComponentesDigitaisSolicitados->processo);
        foreach ($arrObjItensSolicitados as $objItemSolicitado) {

          $objItemSolicitado->hash = is_array($objItemSolicitado->hash) ? $objItemSolicitado->hash : array($objItemSolicitado->hash);

          if($objItemSolicitado->protocolo == $objComponenteDigitalDTO->getStrProtocolo() && in_array($strHashConteudo, $objItemSolicitado->hash)) {
            $objComponenteDigitalDTO->setStrSinEnviar("S");        
          }
        }        
      }

      //TODO: Avaliar dados do tamanho do documento em bytes salvo na base de dados
      $objComponenteDigitalDTO->setNumTamanho($objComponenteDigital->tamanhoEmBytes);
      $objComponenteDigitalDTO->setNumIdAnexo($objComponenteDigital->idAnexo);
      
      $arrObjComponenteDigitalDTO[] = $objComponenteDigitalDTO;
    }

    //Chamada recursiva sobre os documentos dos processos apensados
    if(isset($parObjProcesso->processoApensado) && count($parObjProcesso->processoApensado)) {
      foreach ($parObjProcesso->processoApensado as $objProcessoApensado) {
        $arrObj = $this->montarDadosComponenteDigital($parStrNumeroRegistro, $parNumIdentificacaoTramite, $objProcessoApensado, $parObjComponentesDigitaisSolicitados);
        $arrObjComponenteDigitalDTO = array_merge($arrObjComponenteDigitalDTO, $arrObj); 
      }
    }

    return $arrObjComponenteDigitalDTO;
  }


  public function receberComponenteDigital($parNumIdentificacaoTramite, $parStrHashComponenteDigital, $parStrProtocolo) 
  {
    try
    {    
      $parametros = new stdClass();
      $parametros->parametrosParaRecebimentoDeComponenteDigital = new stdClass();
      $parametros->parametrosParaRecebimentoDeComponenteDigital->identificacaoDoComponenteDigital = new stdClass();
      $parametros->parametrosParaRecebimentoDeComponenteDigital->identificacaoDoComponenteDigital->IDT = $parNumIdentificacaoTramite;
      $parametros->parametrosParaRecebimentoDeComponenteDigital->identificacaoDoComponenteDigital->protocolo = $parStrProtocolo;
      $parametros->parametrosParaRecebimentoDeComponenteDigital->identificacaoDoComponenteDigital->hashDoComponenteDigital = $parStrHashComponenteDigital;            

      return $this->getObjPenWs()->receberComponenteDigital($parametros);

    } catch (\SoapFault $fault) {
            //error_log("REQUEST:" . $this->getObjPenWs()->__getLastRequest());
            //error_log("ERROR:" . print_r($fault, true));
      $mensagem = $this->tratarFalhaWebService($fault);

            //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
            //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      error_log($mensagem);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }        
  }    

   /**
    * Consulta os tramites recusados
    * 
    * @return array
    */
   public function consultarTramitesRecusados($parNumIdRespositorio, $parNumIdEstrutura) {
        try {
            
            $parametro = (object)array(
                'filtroDeConsultaDeTramites' => (object)array(
                    'situacaoAtual' => 9,
                    'remetente' => (object)array(
                        'identificacaoDoRepositorioDeEstruturas' => $parNumIdRespositorio,
                        'numeroDeIdentificacaoDaEstrutura' => $parNumIdEstrutura
                    )
                )
            );
            
            $objTramitesEncontrados = $this->getObjPenWs()->consultarTramites($parametro);
            
            $arrObjTramite = array();
            
            if (isset($objTramitesEncontrados->tramitesEncontrados)) {

                $arrObjTramite = $objTramitesEncontrados->tramitesEncontrados->tramite;
                
                if(!is_array($arrObjTramite)) {
                    $arrObjTramite = array($objTramitesEncontrados->tramitesEncontrados->tramite);
                }
            }

            return $arrObjTramite; 
        } 
        catch (\SoapFault $fault) {
            throw new InfraException(InfraString::formatarJavaScript($this->tratarFalhaWebService($fault)), $fault);
        } 
        catch (\Exception $e) {
            throw new InfraException("Error Processing Request", $e);
        }
    }

  public function consultarTramites($parNumIdTramite = null, $parNumeroRegistro = null, $parNumeroUnidadeRemetente = null, $parNumeroUnidadeDestino = null, $parProtocolo = null, $parNumeroRepositorioEstruturas = null) 
  {
    try
    {    
      $arrObjTramite = array();
      $parametro = new stdClass();
      $parametro->filtroDeConsultaDeTramites = new stdClass();
      $parametro->filtroDeConsultaDeTramites->IDT = $parNumIdTramite;
      $parametro->filtroDeConsultaDeTramites->NRE = $parNumeroRegistro;
      
      if(!is_null($parNumeroUnidadeRemetente) && !is_null($parNumeroRepositorioEstruturas)){
          $parametro->filtroDeConsultaDeTramites->remetente->identificacaoDoRepositorioDeEstruturas = $parNumeroRepositorioEstruturas;
          $parametro->filtroDeConsultaDeTramites->remetente->numeroDeIdentificacaoDaEstrutura = $parNumeroUnidadeRemetente;
      }
      
      if(!is_null($parNumeroUnidadeDestino) && !is_null($parNumeroRepositorioEstruturas)){
          $parametro->filtroDeConsultaDeTramites->destinatario->identificacaoDoRepositorioDeEstruturas = $parNumeroRepositorioEstruturas;
          $parametro->filtroDeConsultaDeTramites->destinatario->numeroDeIdentificacaoDaEstrutura = $parNumeroUnidadeDestino;
      }
      
      if(!is_null($parProtocolo)){
          $parametro->filtroDeConsultaDeTramites->protocolo = $parProtocolo;
      }
      
      $objTramitesEncontrados = $this->getObjPenWs()->consultarTramites($parametro);

      if(isset($objTramitesEncontrados->tramitesEncontrados)) {

        $arrObjTramite = $objTramitesEncontrados->tramitesEncontrados->tramite;
        if(!is_array($arrObjTramite)) {
          $arrObjTramite = array($objTramitesEncontrados->tramitesEncontrados->tramite);
        }
      }

      return $arrObjTramite;

    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }        
  }        
  
  public function consultarTramitesProtocolo($parProtocoloFormatado) 
  {
    try
    {    
      $arrObjTramite = array();
      $parametro = new stdClass();
      $parametro->filtroDeConsultaDeTramites = new stdClass();
      $parametro->filtroDeConsultaDeTramites->protocolo = $parProtocoloFormatado;

      $objTramitesEncontrados = $this->getObjPenWs()->consultarTramites($parametro);

      if(isset($objTramitesEncontrados->tramitesEncontrados)) {

        $arrObjTramite = $objTramitesEncontrados->tramitesEncontrados->tramite;
        if(!is_array($arrObjTramite)) {
          $arrObjTramite = array($objTramitesEncontrados->tramitesEncontrados->tramite);
        }
      }

      return $arrObjTramite;

    } catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }        
  }
  
  /**
   * Retorna o estado atual do procedimento no api-pen
   * 
   * @param integer $dblIdProcedimento
   * @param integer $numIdRepositorio
   * @param integer $numIdEstrutura
   * @return integer
   */
  public function consultarEstadoProcedimento($strProtocoloFormatado = '', $numIdRepositorio = null, $numIdEstrutura = null) {

        $objBD = new GenericoBD($this->inicializarObjInfraIBanco());
      
        $objProtocoloDTO = new ProtocoloDTO();
        $objProtocoloDTO->setStrProtocoloFormatado($strProtocoloFormatado);
        $objProtocoloDTO->setNumMaxRegistrosRetorno(1);
        $objProtocoloDTO->retDblIdProtocolo();
        $objProtocoloDTO->retStrProtocoloFormatado();
        $objProtocoloDTO->retStrStaEstado();
        
        $objProtocoloDTO = $objBD->consultar($objProtocoloDTO);

        if (empty($objProtocoloDTO)) {
            throw new InfraException(utf8_encode(sprintf('Nenhum procedimento foi encontrado com o id %s', $strProtocoloFormatado)));
        }

        if (!in_array($objProtocoloDTO->getStrStaEstado(), array(ProtocoloRN::$TE_EM_PROCESSAMENTO, ProtocoloRn::$TE_BLOQUEADO))) {
            throw new InfraException(utf8_encode('O processo n�o esta com o estado com "Em Processamento" ou "Bloqueado"'));
        }

        $objFiltro = new stdClass();
        $objFiltro->filtroDeConsultaDeTramites = new stdClass();
        $objFiltro->filtroDeConsultaDeTramites->protocolo = $objProtocoloDTO->getStrProtocoloFormatado();

        $objResultado = $this->getObjPenWs()->consultarTramites($objFiltro);

        $objTramitesEncontrados = $objResultado->tramitesEncontrados;        
        
        if (empty($objTramitesEncontrados) || !isset($objTramitesEncontrados->tramite)) {
            throw new InfraException(utf8_encode(sprintf('Nenhum tramite foi encontrado para o procedimento %s', $strProtocoloFormatado)));
        }

        if(!is_array($objTramitesEncontrados->tramite)){
            $objTramitesEncontrados->tramite = array($objTramitesEncontrados->tramite);
        }
        
        $arrObjTramite = (array) $objTramitesEncontrados->tramite;
        
        $objTramite = array_pop($arrObjTramite);
        
        if (empty($numIdRepositorio)) {
            $objInfraParametro = new InfraParametro($this->inicializarObjInfraIBanco());
            $numIdRepositorio = $objInfraParametro->getValor('PEN_ID_REPOSITORIO_ORIGEM');
        }

        if (empty($numIdEstrutura)) {
            
            $objPenUnidadeDTO = new PenUnidadeDTO();
            $objPenUnidadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
            $objPenUnidadeDTO->retNumIdUnidadeRH();

            $objPenUnidadeDTO = $objBD->consultar($objPenUnidadeDTO);

            if (empty($objPenUnidadeDTO)) {
                throw new InfraException(utf8_encode('N�mero da Unidade RH n�o foi encontrado'));
            }
            
            $numIdEstrutura = $objPenUnidadeDTO->getNumIdUnidadeRH();
        }

        if ($objTramite->remetente->numeroDeIdentificacaoDaEstrutura != $numIdEstrutura ||
            $objTramite->remetente->identificacaoDoRepositorioDeEstruturas != $numIdRepositorio) {
            
            throw new InfraException(utf8_encode('O �ltimo tr�mite desse processo n�o pertence a esse �rg�o'));
        }
        
        switch ($objTramite->situacaoAtual) {

            case static::$STA_SITUACAO_TRAMITE_RECIBO_ENVIADO_DESTINATARIO:
                // @todo: caso command-line informar o procedimento que ser� executado
                $objPenTramiteProcessadoRN = new PenTramiteProcessadoRN(PenTramiteProcessadoRN::STR_TIPO_RECIBO);
        
                if(!$objPenTramiteProcessadoRN->isProcedimentoRecebido($objTramite->IDT)){

                    $objReceberReciboTramiteRN = new ReceberReciboTramiteRN();
                    $objReceberReciboTramiteRN->receberReciboDeTramite($objTramite->IDT);
                }
                break;

            case static::$STA_SITUACAO_TRAMITE_RECIBO_RECEBIDO_REMETENTE:
                throw new InfraException(utf8_encode('A expedi��o desse processo j� est� conclu�da'));
                break;

            default:
                $objAtividadeDTO = new AtividadeDTO();
                $objAtividadeDTO->setDblIdProtocolo($objProtocoloDTO->getDblIdProtocolo());
                $objAtividadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
                $objAtividadeDTO->setNumIdUsuario(SessaoSEI::getInstance()->getNumIdUsuario());
                $objAtividadeDTO->setNumIdTarefa(ProcessoEletronicoRN::$TI_PROCESSO_ELETRONICO_PROCESSO_ABORTADO);
                $objAtividadeDTO->setArrObjAtributoAndamentoDTO(array());

                $objAtividadeRN = new AtividadeRN();
                $objAtividadeRN->gerarInternaRN0727($objAtividadeDTO);

                $objProtocoloDTO->setStrStaEstado(ProtocoloRN::$TE_NORMAL);
                $objBD->alterar($objProtocoloDTO);
                
                if($objTramite->situacaoAtual == static::$STA_SITUACAO_TRAMITE_COMPONENTES_RECEBIDOS_DESTINATARIO && $objTramite->situacaoAtual == static::$STA_SITUACAO_TRAMITE_METADADOS_RECEBIDO_DESTINATARIO){
                    $this->cancelarTramite($objTramite->IDT);
                }
                
                return PenConsoleRN::format(sprintf('Processo %s foi atualizado com sucesso', $objProtocoloDTO->getStrProtocoloFormatado()), 'blue'); 
        }
    }

    public function enviarReciboDeTramite($parNumIdTramite, $parDthRecebimento, $parStrReciboTramite) 
  {
    try
    {    
      $strHashAssinatura = null;
      $objPrivatekey = openssl_pkey_get_private("file://".$this->strLocalCert, $this->strLocalCertPassword);

      if ($objPrivatekey === FALSE) {
        throw new InfraException("Erro ao obter chave privada do certificado digital.");                
      }

    //  $recibo =  $parStrReciboTramite;
      
      
      openssl_sign($parStrReciboTramite, $strHashAssinatura, $objPrivatekey, 'sha256');    
      $strHashDaAssinaturaBase64 = base64_encode($strHashAssinatura);

      $parametro = new stdClass();
      $parametro->dadosDoReciboDeTramite = new stdClass();
      $parametro->dadosDoReciboDeTramite->IDT = $parNumIdTramite;
      $parametro->dadosDoReciboDeTramite->dataDeRecebimento = $parDthRecebimento;
      $parametro->dadosDoReciboDeTramite->hashDaAssinatura = $strHashDaAssinaturaBase64;
      
     // throw new InfraException('TESTE '.var_export($parametro, true)." DELIMITADOR ".$recibo);
      
      return $this->getObjPenWs()->enviarReciboDeTramite($parametro);

    } catch (\SoapFault $fault) {
        
            $strMensagem  = '[ SOAP Request ]'.PHP_EOL;
            $strMensagem .= 'Method: enviarReciboDeTramite (FAIL)'.PHP_EOL;
            $strMensagem .= 'Request: '.$this->getObjPenWs()->__getLastRequest().PHP_EOL;
            $strMensagem .= 'Response: '.$this->getObjPenWs()->__getLastResponse().PHP_EOL;
            
            file_put_contents('/tmp/pen.log', $strMensagem.PHP_EOL, FILE_APPEND);
        
      if(isset($objPrivatekey)){
        openssl_free_key($objPrivatekey);
      }

            //error_log("REQUEST:" . $this->getObjPenWs()->__getLastRequest());
            //error_log("ERROR:" . print_r($fault, true));
      $mensagem = $this->tratarFalhaWebService($fault);

            //TODO: Remover formata��o do javascript ap�s resolu��o do BUG enviado para Mairon
            //relacionado ao a renderiza��o de mensagens de erro na barra de progresso
      error_log($mensagem);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);

    } catch (\Exception $e) {
      if(isset($objPrivatekey)){
        openssl_free_key($objPrivatekey);
      }

      throw new InfraException("Error Processing Request", $e);
    }  
  }        

  public function receberReciboDeTramite($parNumIdTramite) 
  {
    try
    {    
      $parametro = new stdClass();
      $parametro->IDT = $parNumIdTramite;

      $resultado = $this->getObjPenWs()->receberReciboDeTramite($parametro);

      $objReciboTramiteDTO = null;
      if(isset($resultado)) {
        $objConteudoDoReciboDeTramite = $resultado->conteudoDoReciboDeTramite;
        $objReciboTramiteDTO = new ReciboTramiteDTO();
        $objReciboTramiteDTO->setStrNumeroRegistro($objConteudoDoReciboDeTramite->recibo->NRE);
        $objReciboTramiteDTO->setNumIdTramite($objConteudoDoReciboDeTramite->recibo->IDT);
        $objDateTime = new DateTime($objConteudoDoReciboDeTramite->recibo->dataDeRecebimento);        
        $objReciboTramiteDTO->setDthRecebimento($objDateTime->format('d/m/Y H:i:s'));
        //TODO: Avaliar se o resultado corresponde � uma lista de hashs ou apenas um elemento
        //$objReciboTramiteDTO->setStrHashComponenteDigital();
        $objReciboTramiteDTO->setStrCadeiaCertificado($objConteudoDoReciboDeTramite->cadeiaDoCertificado);
        $objReciboTramiteDTO->setStrHashAssinatura($objConteudoDoReciboDeTramite->hashDaAssinatura);
      }

      return $objReciboTramiteDTO;
    } 
    catch (\SoapFault $fault) {
      $mensagem = $this->tratarFalhaWebService($fault);
      throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
    } catch (\Exception $e) {
      throw new InfraException("Error Processing Request", $e);
    }        
  }     
  
    /**
     * Retorna um objeto DTO do recibo de envio do processo ao barramento
     * 
     * @param int $parNumIdTramite
     * @return ReciboTramiteEnviadoDTO
     */
    public function receberReciboDeEnvio($parNumIdTramite) {
        
        try {
            $parametro = new stdClass();
            $parametro->IDT = $parNumIdTramite;

            $resultado = $this->getObjPenWs()->receberReciboDeEnvio($parametro);

            if ($resultado && $resultado->conteudoDoReciboDeEnvio) {
                
                $objNodo = $resultado->conteudoDoReciboDeEnvio;
                $objReciboTramiteDTO = new ReciboTramiteEnviadoDTO();
                $objReciboTramiteDTO->setStrNumeroRegistro($objNodo->reciboDeEnvio->NRE);
                $objReciboTramiteDTO->setNumIdTramite($objNodo->reciboDeEnvio->IDT);
                $objDateTime = new DateTime($objNodo->reciboDeEnvio->dataDeRecebimento);
                $objReciboTramiteDTO->setDthRecebimento($objDateTime->format('d/m/Y H:i:s'));
                $objReciboTramiteDTO->setStrCadeiaCertificado($objNodo->cadeiaDoCertificado);
                $objReciboTramiteDTO->setStrHashAssinatura($objNodo->hashDaAssinatura);
                
                return $objReciboTramiteDTO;
            }
        } 
        catch (\SoapFault $fault) {
            $mensagem = $this->tratarFalhaWebService($fault);
            throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
        } 
        catch (\Exception $e) {
            throw new InfraException("Error Processing Request", $e);
        } 
        throw new InfraException("Error Processing Request", $e);
    }

    //TODO: Implementar mapeamento entre opera��es do PEN e tarefas do SEI
  public function converterOperacaoDTO($objOperacaoPEN)
  {
    if(!isset($objOperacaoPEN)) {
      throw new InfraException('Par�metro $objOperacaoPEN n�o informado.');            
    }

    $objOperacaoDTO = new OperacaoDTO();
    $objOperacaoDTO->setStrCodigo(utf8_decode($objOperacaoPEN->codigo));
    $objOperacaoDTO->setStrComplemento(utf8_decode($objOperacaoPEN->complemento));
    $objOperacaoDTO->setDthOperacao($this->converterDataSEI($objOperacaoPEN->dataHora));

    $strIdPessoa =  ($objOperacaoPEN->pessoa->numeroDeIdentificacao) ?: null;
    $objOperacaoDTO->setStrIdentificacaoPessoaOrigem(utf8_decode($strIdPessoa));

    $strNomePessoa =  ($objOperacaoPEN->pessoa->nome) ?: null;
    $objOperacaoDTO->setStrNomePessoaOrigem(utf8_decode($strNomePessoa));

    switch ($objOperacaoPEN->codigo) {
      case "01": $objOperacaoDTO->setStrNome("Registro"); break;
      case "02": $objOperacaoDTO->setStrNome("Envio de documento avulso/processo"); break;
      case "03": $objOperacaoDTO->setStrNome("Cancelamento/exclus�o ou envio de documento"); break;
      case "04": $objOperacaoDTO->setStrNome("Recebimento de documento"); break;
      case "05": $objOperacaoDTO->setStrNome("Autua��o"); break;
      case "06": $objOperacaoDTO->setStrNome("Juntada por anexa��o"); break;
      case "07": $objOperacaoDTO->setStrNome("Juntada por apensa��o"); break;
      case "08": $objOperacaoDTO->setStrNome("Desapensa��o"); break;
      case "09": $objOperacaoDTO->setStrNome("Arquivamento"); break;
      case "10": $objOperacaoDTO->setStrNome("Arquivamento no Arquivo Nacional"); break;
      case "11": $objOperacaoDTO->setStrNome("Elimina��o"); break;
      case "12": $objOperacaoDTO->setStrNome("Sinistro"); break;
      case "13": $objOperacaoDTO->setStrNome("Reconstitui��o de processo"); break;
      case "14": $objOperacaoDTO->setStrNome("Desarquivamento"); break;
      case "15": $objOperacaoDTO->setStrNome("Desmembramento"); break;
      case "16": $objOperacaoDTO->setStrNome("Desentranhamento"); break;
      case "17": $objOperacaoDTO->setStrNome("Encerramento/abertura de volume no processo"); break;
      case "18": $objOperacaoDTO->setStrNome("Registro de extravio"); break;            
      default:   $objOperacaoDTO->setStrNome("Registro"); break;
    }

    return $objOperacaoDTO;
  } 

    //TODO: Implementar mapeamento entre opera��es do PEN e tarefas do SEI
  public function obterCodigoOperacaoPENMapeado($numIdTarefa)
  {
    $strCodigoOperacao = self::$OP_OPERACAO_REGISTRO;

    if(isset($numIdTarefa) && $numIdTarefa != 0) {        
      $objRelTarefaOperacaoDTO = new RelTarefaOperacaoDTO();
      $objRelTarefaOperacaoDTO->retStrCodigoOperacao();
      $objRelTarefaOperacaoDTO->setNumIdTarefa($numIdTarefa);


      $objRelTarefaOperacaoBD = new RelTarefaOperacaoBD(BancoSEI::getInstance());
      $objRelTarefaOperacaoDTO = $objRelTarefaOperacaoBD->consultar($objRelTarefaOperacaoDTO);

      if($objRelTarefaOperacaoDTO != null) {
        $strCodigoOperacao = $objRelTarefaOperacaoDTO->getStrCodigoOperacao();            
      }
    }

    return $strCodigoOperacao;
  } 

    //TODO: Implementar mapeamento entre opera��es do PEN e tarefas do SEI
  public function obterIdTarefaSEIMapeado($strCodigoOperacao)
  {
    return self::$TI_PROCESSO_ELETRONICO_PROCESSO_TRAMITE_EXTERNO;
  } 
  
   
  /**
   * Cancela um tramite de expedi��o de um procedimento para outra unidade, gera
   * falha caso a unidade de destino j� tenha come�ado a receber o procedimento.
   * 
   * @param type $idTramite
   * @param type $idProtocolo
   * @throws Exception|InfraException
   * @return null
   */
  public function cancelarTramite($idTramite) {

      //Faz a consulta do tramite
      $paramConsultaTramite = new stdClass();
      $paramConsultaTramite->filtroDeConsultaDeTramites->IDT = $idTramite;
      $dadosTramite = $this->getObjPenWs()->consultarTramites($paramConsultaTramite);

      //Requisita o cancelamento
      $parametros = new stdClass();
      $parametros->IDT = $idTramite;
      
      try{
          $this->getObjPenWs()->cancelarEnvioDeTramite($parametros);
      } 
      catch(\SoapFault $e) {
          throw new InfraException($e->getMessage(), null, $e);
      }      
  }
  
  /**
   * M�todo que faz a recusa de um tr�mite
   * 
   * @param integer $idTramite
   * @param string $justificativa
   * @param integer $motivo
   * @return mixed
   * @throws InfraException
   */
  public function recusarTramite($idTramite, $justificativa, $motivo) {
        try {

            $parametros = new stdClass();
            $parametros->recusaDeTramite->IDT = $idTramite;
            $parametros->recusaDeTramite->justificativa = utf8_encode($justificativa);
            $parametros->recusaDeTramite->motivo = $motivo;

            $resultado = $this->getObjPenWs()->recusarTramite($parametros);
            
        } catch (SoapFault $fault) {

            $mensagem = $this->tratarFalhaWebService($fault);
            throw new InfraException(InfraString::formatarJavaScript($mensagem), $fault);
            
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function cadastrarTramitePendente($numIdentificacaoTramite, $idAtividadeExpedicao) {
        try {

            $tramitePendenteDTO = new TramitePendenteDTO();
            $tramitePendenteDTO->setNumIdTramite($numIdentificacaoTramite);
            $tramitePendenteDTO->setNumIdAtividade($idAtividadeExpedicao);

            $tramitePendenteBD = new TramitePendenteBD($this->getObjInfraIBanco());
            $tramitePendenteBD->cadastrar($tramitePendenteDTO);
            
        } catch (\InfraException $ex) {
            throw new InfraException($ex->getDescricao());
        } catch (\Exception $ex) {
            throw new InfraException($ex->getMessage());
        }
    }

    public function isDisponivelCancelarTramite($strProtocolo = ''){

        $objInfraParametro = new InfraParametro($this->inicializarObjInfraIBanco());
        $numIdRespositorio = $objInfraParametro->getValor('PEN_ID_REPOSITORIO_ORIGEM');
        
        $objPenUnidadeDTO = new PenUnidadeDTO();
        $objPenUnidadeDTO->setNumIdUnidade(SessaoSEI::getInstance()->getNumIdUnidadeAtual());
        $objPenUnidadeDTO->retNumIdUnidadeRH();
                
        $objGenericoBD = new GenericoBD($this->inicializarObjInfraIBanco());
        $objPenUnidadeDTO = $objGenericoBD->consultar($objPenUnidadeDTO);
         
        try {

            $parametro = (object)array(
                'filtroDeConsultaDeTramites' => (object)array(
                    'remetente' => (object)array(
                        'identificacaoDoRepositorioDeEstruturas' => $numIdRespositorio,
                        'numeroDeIdentificacaoDaEstrutura' => $objPenUnidadeDTO->getNumIdUnidadeRH()
                    ),
                    'protocolo' => $strProtocolo
                )
            );
    
          
            $objMeta = $this->getObjPenWs()->consultarTramites($parametro);
            
            
            if($objMeta->tramitesEncontrados) {
                
                $arrObjMetaTramite = !is_array($objMeta->tramitesEncontrados->tramite) ? array($objMeta->tramitesEncontrados->tramite) : $objMeta->tramitesEncontrados->tramite;
                
                $objMetaTramite = array_pop($arrObjMetaTramite);

                switch($objMetaTramite->situacaoAtual){

                    case static::$STA_SITUACAO_TRAMITE_INICIADO:
                    case static::$STA_SITUACAO_TRAMITE_COMPONENTES_ENVIADOS_REMETENTE:
                    case static::$STA_SITUACAO_TRAMITE_METADADOS_RECEBIDO_DESTINATARIO:
                    case static::$STA_SITUACAO_TRAMITE_COMPONENTES_RECEBIDOS_DESTINATARIO:
                        return true;
                        break;

                }
            }
            
            return false;
        }
        catch(SoapFault $e) {
            throw new InfraException($e->getMessage());
        }
        catch(Exception $e) {
            throw new InfraException($e->getMessage());
        }
    }
}



/*

        -- $TI_PROCESSO_ELETRONICO_PROCESSO_EXPEDIDO = 501
        DELETE FROM tarefa where id_tarefa = 501;
        INSERT INTO tarefa (id_tarefa, nome, sin_historico_resumido, sin_historico_completo, sin_fechar_andamentos_abertos, sin_lancar_andamento_fechado, sin_permite_processo_fechado)
        values(501, 'Processo expedido para a entidade @UNIDADE_DESTINO@ - @REPOSITORIO_DESTINO@ (@PROCESSO@, @UNIDADE@, @USUARIO@)', 'S', 'S', 'N', 'S', 'N');


 */