<?php

require_once dirname(__FILE__) . '/../../../SEI.php';

class ProcessarPendenciasRN extends InfraAgendamentoTarefa {

  private static $instance = null;
  private $objGearmanWorker = null;

  protected function inicializarObjInfraIBanco(){
    return BancoSEI::getInstance();
  }

  public static function getInstance() {
    if (self::$instance == null) {
      self::$instance = new ProcessarPendenciasRN(ConfiguracaoSEI::getInstance(), SessaoSEI::getInstance(), BancoSEI::getInstance(), LogSEI::getInstance());
    }
    return self::$instance;
  }  

  public function __construct() {
    //Configura��o do worker do Gearman para realizar o processamento de tarefas
    $this->objGearmanWorker = new GearmanWorker();
    $this->objGearmanWorker->addServer('localhost', 4730);
    $this->configurarCallbacks();
  }

  public function processarPendencias()
  {
    try{
      ini_set('max_execution_time','0');
      ini_set('memory_limit','-1');

      InfraDebug::getInstance()->setBolLigado(true);
      InfraDebug::getInstance()->setBolDebugInfra(true);
      InfraDebug::getInstance()->setBolEcho(false);
      InfraDebug::getInstance()->limpar();

      SessaoSEI::getInstance(false)->simularLogin(SessaoSEI::$USUARIO_SEI, SessaoSEI::$UNIDADE_TESTE);

      $numSeg = InfraUtil::verificarTempoProcessamento();
      
      InfraDebug::getInstance()->gravar('ANALISANDO OS TR�MITES PENDENTES ENVIADOS PARA O �RG�O (PEN)');
      echo "[".date("d/m/Y H:i:s")."] Iniciando servi�o de processamento de pend�ncias de tr�mites de processos...\n";

      while($this->objGearmanWorker->work())
      {
        if ($this->objGearmanWorker->returnCode() != GEARMAN_SUCCESS)
        {
          $strAssunto = 'Erro executando agendamentos';
          $strErro = InfraException::inspecionar($e);      
          echo $strAssunto."\n\n".$strErro;
          LogSEI::getInstance()->gravar($strAssunto."\n\n".$strErro); 
          break;
        }
      }

      $numSeg = InfraUtil::verificarTempoProcessamento($numSeg);
      InfraDebug::getInstance()->gravar('TEMPO TOTAL DE EXECUCAO: '.$numSeg.' s');
      InfraDebug::getInstance()->gravar('FIM');
      LogSEI::getInstance()->gravar(InfraDebug::getInstance()->getStrDebug());
    } 
    catch(Exception $e){
      $strAssunto = 'Agendamento FALHOU';
      $strErro = '';
      $strErro .= 'Servidor: '.gethostname()."\n\n";
      $strErro .= 'Data/Hora: '.InfraData::getStrDataHoraAtual()."\n\n";
      $strErro .= 'Erro: '.InfraException::inspecionar($e);
      LogSEI::getInstance()->gravar($strAssunto."\n\n".$strErro); 
    }
  }

  private function configurarCallbacks() 
  {
    //PROCESSAMENTO DE TAREFAS RELACIONADAS AO ENVIO DE UM PROCESSO ELETR�NICO
    //////////////////////////////////////////////////////////////////////////

    //Etapa 01 - Processamento de pend�ncias envio dos metadados do processo
    $this->objGearmanWorker->addFunction("enviarProcesso", function ($job) {

      InfraDebug::getInstance()->gravar("[".date("d/m/Y H:i:s")."] Processando tarefa [enviarProcesso] " . $job->workload());
      //TODO: Implementar tarefa relacionada
      //...

      //Agendamento de nova tarefa para envio dos componentes digitais do processo
      //$this->objGearmanClient->addTask("enviarComponenteDigital", $numIdentificacaoTramite, null);

    });

    //Etapa 02 - Processamento de pend�ncias envio dos componentes digitais do processo
    $this->objGearmanWorker->addFunction("enviarComponenteDigital", function ($job) {

      InfraDebug::getInstance()->gravar("[".date("d/m/Y H:i:s")."] Processando tarefa [enviarComponenteDigital] " . $job->workload());
      //TODO: Implementar tarefa relacionada
      //...

      //Agendamento de nova tarefa para recebimento do recibo de envio do processo
      //$this->objGearmanClient->addTask("receberReciboTramite", $numIdentificacaoTramite, null);

    });

    //Etapa 03 - Processamento de pend�ncias de recebimento do recibo de envio do processo
    $this->objGearmanWorker->addFunction("receberReciboTramite", function ($job) {
        
        $numIdentificacaoTramite = intval($job->workload());
        
        InfraDebug::getInstance()->gravar("[".date("d/m/Y H:i:s")."] Processando tarefa [receberReciboTramite] " . $job->workload());
        //TODO: Implementar tarefa relacionada
        
        $objPenTramiteProcessadoRN = new PenTramiteProcessadoRN(PenTramiteProcessadoRN::STR_TIPO_RECIBO);
        
        if(!$objPenTramiteProcessadoRN->isProcedimentoRecebido($numIdentificacaoTramite)){
            
            $objReceberReciboTramiteRN = new ReceberReciboTramiteRN();
            $objReceberReciboTramiteRN->receberReciboDeTramite($numIdentificacaoTramite);
        }
    });


    //PROCESSAMENTO DE TAREFAS RELACIONADAS AO RECEBIMENTO DE UM PROCESSO ELETR�NICO
    //////////////////////////////////////////////////////////////////////////

    //Processamento de pend�ncias de recebimento dos metadados do processo
    $this->objGearmanWorker->addFunction("receberProcedimento", function ($job) {
      
        $numIdentificacaoTramite = intval($job->workload());

        InfraDebug::getInstance()->gravar("[".date("d/m/Y H:i:s")."] Processando tarefa [receberProcedimento] " . $job->workload());
               
        $objPenTramiteProcessadoRN = new PenTramiteProcessadoRN(PenTramiteProcessadoRN::STR_TIPO_PROCESSO);
        
        if(!$objPenTramiteProcessadoRN->isProcedimentoRecebido($numIdentificacaoTramite)){
            
            $objReceberProcedimentoRN = new ReceberProcedimentoRN(); 
            $objReceberProcedimentoRN->receberProcedimento($numIdentificacaoTramite);
            
            //TODO: A pr�xima etapa deveria ser o recebimento dos componentes digitais, rotina tradada na fun��o receberProcedimento(...)
            //Agendamento de nova tarefa para envio do recibo de conclus�o do tr�mite
            //ProcessarPendenciasRN::processarTarefa("enviarReciboTramiteProcesso", $job->workload());
            
          /*  $objEnviarReciboTramiteRN = new EnviarReciboTramiteRN();
            $objEnviarReciboTramiteRN->enviarReciboTramiteProcesso($numIdentificacaoTramite, $arrayHash);*/
        }
    });
    
    // Verifica no barramento os procedimentos que foram enviados por esta unidade
    // e foram recusados pelas mesmas
    $this->objGearmanWorker->addFunction("receberTramitesRecusados", function ($job) {
      
        InfraDebug::getInstance()->gravar("[".date("d/m/Y H:i:s")."] Processando tarefa [receberRecusaTramite] " . $job->workload());

        $objReceberProcedimentoRN = new ReceberProcedimentoRN();  
        $objReceberProcedimentoRN->receberTramitesRecusados();
    });
    
    //Processamento de pend�ncias de recebimento dos componentes digitais do processo
    $this->objGearmanWorker->addFunction("receberComponenteDigital", function ($job) {
      
      InfraDebug::getInstance()->gravar("[".date("d/m/Y H:i:s")."] Processando tarefa [receberComponenteDigital] " . $job->workload());
      //TODO: A pr�xima etapa deveria ser o recebimento dos componentes digitais, rotina tradada na fun��o receberProcedimento(...)
      //...    

      //Agendamento de nova tarefa para envio do recibo de conclus�o do tr�mite
      ProcessarPendenciasRN::processarTarefa("enviarReciboTramiteProcesso", $job->workload());
      //$this->objGearmanClient->addTaskBackground("enviarReciboTramiteProcesso", $numIdentificacaoTramite, null);
    });

    //Processamento de pend�ncias de envio do recibo de conclus�o do tr�mite do processo
    $this->objGearmanWorker->addFunction("enviarReciboTramiteProcesso", function ($job) {

      InfraDebug::getInstance()->gravar("[".date("d/m/Y H:i:s")."] Processando tarefa [enviarReciboTramiteProcesso] " . $job->workload());

      $numIdentificacaoTramite = intval($job->workload());
      $objEnviarReciboTramiteRN = new EnviarReciboTramiteRN();
      $objEnviarReciboTramiteRN->enviarReciboTramiteProcesso($numIdentificacaoTramite);
    });
  }

  static function processarTarefa($strNomeTarefa, $strWorkload)
  {
    $objClient = new GearmanClient();    
    $objClient->addServer('localhost', 4730);
    //$objClient->addTaskBackground($strNomeTarefa, $strWorkload);
    //$objClient->runTasks();
    $objClient->doBackground($strNomeTarefa, $strWorkload);
  }
}

//TODO: Tratar envio de e-mail em caso de falhas de execu��o
SessaoSEI::getInstance(false);
ProcessarPendenciasRN::getInstance()->processarPendencias();

?>