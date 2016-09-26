<?php

require_once dirname(__FILE__) . '/../../../SEI.php';

/**
 * Controla o log de estados da expadi��o de um procedimento pelo modulo SEI
 * 
 * @autor Join Tecnologia
 */
class ProcedimentoAndamentoRN extends InfraRN {
    
    protected $isSetOpts = false;
    protected $dblIdProcedimento;
    protected $dblIdTramit;
    protected $numTarefa = 501;    

    /**
     * Inst�ncia do driver de conex�o com o banco de dados
     * 
     * @var Infra[Driver]
     */
    protected $bancoSEI = null;
    
    public function __destruct() {
        
        if(!empty($this->bancoSEI)) {
            
            $this->bancoSEI->fecharConexao();
        }
    }
    
    /**
     * Inv�s de aproveitar o singleton do BancoSEI criamos uma nova inst�ncia para
     * n�o ser afetada pelo transation
     * 
     * @return Infra[Driver]
     */
    protected function inicializarObjInfraIBanco() {
        
        if(empty($this->bancoSEI)) {
            
            $this->bancoSEI = new BancoSEI();
            $this->bancoSEI->abrirConexao();
        }
        
        return $this->bancoSEI;
    }
    
    
    public function setOpts($dblIdProcedimento = 0, $dblIdTramit = 0, $numTarefa = 501){
        
        $this->dblIdProcedimento = $dblIdProcedimento;
        $this->dblIdTramit = $dblIdTramit;
        $this->numTarefa = $numTarefa;
        $this->isSetOpts = true;
    }
    
    /**
     * Adiciona um novo andamento � um procedimento que esta sendo expedido para outra unidade
     * 
     * @param string $strMensagem
     * @param string $strSituacao Tipo ENUM(S,N)
     * @return null
     */
    public function cadastrar($strMensagem = 'N�o informado', $strSituacao = 'N'){
        
        if($this->isSetOpts === false) {
            throw new InfraException('Log do cadastro de procedimento n�o foi configurado');
        }
        
        $objInfraIBanco = $this->inicializarObjInfraIBanco();

        $objInfraIBanco->abrirTransacao();
        
        $hash = md5($this->dblIdProcedimento.$strMensagem);
        
        $objProcedimentoAndamentoDTO = new ProcedimentoAndamentoDTO();
        $objProcedimentoAndamentoDTO->setStrHash($hash);
        $objProcedimentoAndamentoDTO->retTodos();
         
        $objProcedimentoAndamentoBD = new ProcedimentoAndamentoBD($objInfraIBanco);
        $objProcedimentoAndamentoDTO = $objProcedimentoAndamentoBD->consultar($objProcedimentoAndamentoDTO);
    
        
        // achou um registro, ent�o atualiza o log
        if(!empty($objProcedimentoAndamentoDTO)) {
            
            $objProcedimentoAndamentoDTO->setStrSituacao($strSituacao);
            $objProcedimentoAndamentoDTO->setDthData(date('d/m/Y H:i:s'));            
          
            $objProcedimentoAndamentoBD->alterar($objProcedimentoAndamentoDTO);
        }
        // Sen�o cadastra um novo log
        else {            
   
            $objProcedimentoAndamentoDTO = new ProcedimentoAndamentoDTO();
            $objProcedimentoAndamentoDTO->setStrSituacao($strSituacao);
            $objProcedimentoAndamentoDTO->setDthData(date('d/m/Y H:i:s'));            
            $objProcedimentoAndamentoDTO->setDblIdProcedimento($this->dblIdProcedimento);
            $objProcedimentoAndamentoDTO->setDblIdTramite($this->dblIdTramit);
            $objProcedimentoAndamentoDTO->setStrSituacao($strSituacao);
            $objProcedimentoAndamentoDTO->setStrMensagem($strMensagem);
            $objProcedimentoAndamentoDTO->setStrHash($hash);
            $objProcedimentoAndamentoDTO->setNumTarefa($this->numTarefa);
            
            $objProcedimentoAndamentoBD->cadastrar($objProcedimentoAndamentoDTO);
        }
       
        $objInfraIBanco->confirmarTransacao(); 
    }
}