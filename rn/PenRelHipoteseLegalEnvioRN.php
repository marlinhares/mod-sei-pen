<?php

require_once dirname(__FILE__) . '/../../../SEI.php';

/**
 * Description of PenRelHipoteseLegalEnvioRN
 *
 * @author Join Tecnologia
 */
class PenRelHipoteseLegalEnvioRN extends PenRelHipoteseLegalRN {
    
    protected function listarConectado(PenRelHipoteseLegalDTO $objDTO){
        SessaoSEI::getInstance()->validarAuditarPermissao('pen_map_hipotese_legal_envio_listar', __METHOD__, $objDTO);
        return parent::listarConectado($objDTO);
    }

    protected function alterar(PenRelHipoteseLegalDTO $objDTO){
        SessaoSEI::getInstance()->validarAuditarPermissao('pen_map_hipotese_legal_envio_alterar', __METHOD__, $objDTO);
        return parent::alterarConectado($objDTO);
    }

    protected function cadastrar(PenRelHipoteseLegalDTO $objDTO){
        SessaoSEI::getInstance()->validarAuditarPermissao('pen_map_hipotese_legal_envio_cadastrar', __METHOD__, $objDTO);
        return parent::cadastrarConectado($objDTO);
    }

    protected function excluir(PenRelHipoteseLegalDTO $objDTO){
        SessaoSEI::getInstance()->validarAuditarPermissao('pen_map_hipotese_legal_envio_excluir', __METHOD__, $objDTO);
        return parent::excluirConectado($objDTO);
    }

    
    /**
     * Pega o ID hipotese sei para buscar o ID do barramento
     * @param integer $numIdHipoteseSEI
     * @return integer
     */
    protected function getIdHipoteseLegalPENConectado($numIdHipoteseSEI) {
        $objBanco = BancoSEI::getInstance();
        $objGenericoBD = new GenericoBD($objBanco);
        
        // Mapeamento da hipotese legal remota
        $objPenRelHipoteseLegalDTO = new PenRelHipoteseLegalDTO();
        $objPenRelHipoteseLegalDTO->setStrTipo('E');
        $objPenRelHipoteseLegalDTO->retNumIdentificacao();
        $objPenRelHipoteseLegalDTO->setNumIdHipoteseLegal($numIdHipoteseSEI);
                
        $objPenRelHipoteseLegal = $objGenericoBD->consultar($objPenRelHipoteseLegalDTO);
        
        if ($objPenRelHipoteseLegal) {
            return $objPenRelHipoteseLegal->getNumIdentificacao();
        } else {
            return null;
        }
    }
}
