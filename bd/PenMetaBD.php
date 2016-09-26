<?php

require_once dirname(__FILE__) . '/../../../SEI.php';

/**
 * Mapeamento dos metadados sobre a estrutura do banco de dados
 *
 * @author Join Tecnologia
 */
class PenMetaBD extends InfraMetaBD {
    
    const NNULLO = 'NOT NULL';
    const SNULLO = 'NULL';

    /**
     * 
     * @return string
     */
    public function adicionarValorPadraoParaColuna($strNomeTabela, $strNomeColuna, $strValorPadrao, $bolRetornarQuery = false){
        
        $objInfraBanco = $this->getObjInfraIBanco();
        
        $strTableDrive = get_parent_class($objInfraBanco);
        $strQuery = '';
        
        switch($strTableDrive) {

            case 'InfraMySql':
                $strQuery = sprintf("ALTER TABLE `%s` ALTER COLUMN `%s` SET DEFAULT '%s'", $strNomeTabela, $strNomeColuna, $strValorPadrao);
                break;
                
            case 'InfraSqlServer':
                 $strQuery =  sprintf("ALTER TABLE [%s] ADD DEFAULT('%s') FOR [%s]", $strNomeTabela, $strValorPadrao, $strNomeColuna);
            
            case 'InfraOracle':
                break;
        }
        
        if($bolRetornarQuery === false) {
            
            $objInfraBanco->executarSql($strQuery);
        }
        else {
        
            return  $strQuery;
        }
    }
    
    /**
     * Verifica se o usu�rio do drive de conex�o possui permiss�o para criar/ remover
     * estruturas
     * 
     * @return PenMetaBD
     */
    public function isDriverPermissao(){
        
        $objInfraBanco = $this->getObjInfraIBanco();

        if(count($this->obterTabelas('sei_teste'))==0){
            $objInfraBanco->executarSql('CREATE TABLE sei_teste (id '.$this->tipoNumero().' NULL)');
        }
      
        $objInfraBanco->executarSql('DROP TABLE sei_teste');
        
        return $this;
    }
    
    /**
     * Verifica se o banco do SEI � suportador pelo atualizador
     * 
     * @throws InfraException
     * @return PenMetaBD
     */
    public function isDriverSuportado(){
        
        $strTableDrive = get_parent_class($this->getObjInfraIBanco());
            
        switch($strTableDrive) {

            case 'InfraMySql':
                // Fix para bug de MySQL vers�o inferior ao 5.5 o default engine
                // � MyISAM e n�o tem suporte a FOREING KEYS
                $this->getObjInfraIBanco()->executarSql('SET STORAGE_ENGINE=InnoDB');  
            case 'InfraSqlServer':
            case 'InfraOracle':
                break;

            default:
                throw new InfraException('BANCO DE DADOS NAO SUPORTADO: ' . $strTableDrive);

        }
        
        return $this;
    }
    
    /**
     * Verifica se a vers�o sistema � compativel com a vers�o do m�dulo PEN
     * 
     * @throws InfraException
     * @return PenMetaBD
     */
    public function isVersaoSuportada($strRegexVersaoSistema, $strVerMinRequirida){
        
        $numVersaoRequerida = intval(preg_replace('/\D+/', '', $strVerMinRequirida));
        $numVersaoSistema = intval(preg_replace('/\D+/', '', $strRegexVersaoSistema));
        
        if($numVersaoRequerida > $numVersaoSistema){
            throw new InfraException('VERSAO DO FRAMEWORK PHP INCOMPATIVEL (VERSAO ATUAL '.$strRegexVersaoSistema.', VERSAO REQUERIDA '.$strVerMinRequirida.')');
        }
        
        return $this;
    }
    
    /**
     * Apaga a chave prim�ria da tabela
     * 
     * @throws InfraException
     * @return PenMetaBD
     */
    public function removerChavePrimaria($strNomeTabela, $strNomeChave){
        
        if($this->isChaveExiste($strNomeTabela, $strNomeChave)) {
        
            $strTableDrive = get_parent_class($this->getObjInfraIBanco());

            switch($strTableDrive) {

                case 'InfraMySql':
                    $this->getObjInfraIBanco()->executarSql('ALTER TABLE '.$strNomeTabela.' DROP PRIMARY KEY');
                    break;

                case 'InfraSqlServer':
                    $this->getObjInfraIBanco()->executarSql('ALTER TABLE '.$strNomeTabela.' DROP CONSTRAINT '.$strNomeChave);
                    break;

                case 'InfraOracle':
                    break;
            }
        }
        return $this;
    }
        
    public function isChaveExiste($strNomeTabela = '', $strNomeChave = ''){
        
        $objInfraBanco = $this->getObjInfraIBanco();
        $strTableDrive = get_parent_class($objInfraBanco);
            
        switch($strTableDrive) {

            case 'InfraMySql':
                $strSql = " SELECT COUNT(CONSTRAINT_NAME) AS EXISTE
                            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                            WHERE CONSTRAINT_SCHEMA = '".$objInfraBanco->getBanco()."'
                            AND TABLE_NAME = '".$strNomeTabela."'
                            AND CONSTRAINT_NAME = '".$strNomeChave."'";
                break;
            
            case 'InfraSqlServer':
                
                $strSql = " SELECT COUNT(CONSTRAINT_NAME) AS EXISTE 
                            FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                            WHERE CONSTRAINT_CATALOG = '".$objInfraBanco->getBanco()."'
                            AND TABLE_NAME = '".$strNomeTabela."'
                            AND CONSTRAINT_NAME = '".$strNomeChave."'";
                break;
                
            case 'InfraOracle':
                 $strSql = "SELECT 0 AS EXISTE";
                break;
        }
        
        $strSql = preg_replace('/\s+/', ' ', $strSql);
        $arrDados = $objInfraBanco->consultarSql($strSql);

        return (intval($arrDados[0]['EXISTE']) > 0) ? true : false;
    }
    
    public function adicionarChaveUnica($strNomeTabela = '', $arrNomeChave = array()){
        
        $this->getObjInfraIBanco()
                ->executarSql('ALTER TABLE '.$strNomeTabela.' ADD CONSTRAINT UK_'.$strNomeTabela.' UNIQUE('.implode(', ', $arrNomeChave).')');
    }
    
    public function renomearTabela($strNomeTabelaAtual, $strNomeTabelaNovo){
        
        if($this->isTabelaExiste($strNomeTabelaAtual)) {
            
            $objInfraBanco = $this->getObjInfraIBanco();
        
            $strTableDrive = get_parent_class($objInfraBanco);
            $strQuery = '';

            switch ($strTableDrive) {

                    case 'InfraMySql':
                        $strQuery = sprintf("ALTER TABLE `%s` RENAME TO `%s`", $strNomeTabelaAtual, $strNomeTabelaNovo);
                        break;

                    case 'InfraSqlServer':
                        $strQuery = sprintf("sp_rename '%s', '%s'", $strNomeTabelaAtual, $strNomeTabelaNovo);

                    case 'InfraOracle':
                        $strQuery = sprintf("RENAME TABLE %s TO %s", $strNomeTabelaAtual, $strNomeTabelaNovo);
                        break;
            }
            
            $objInfraBanco->executarSql($strQuery);
        }
    }
    
    
    /**
     * Verifica se uma tabela existe no banco
     * 
     * @throws InfraException
     * @return bool
     */
    public function isTabelaExiste($strNomeTabela = ''){
        
        $objInfraBanco = $this->getObjInfraIBanco();
        $strTableDrive = get_parent_class($objInfraBanco);
            
        switch($strTableDrive) {

            case 'InfraMySql':
                $strSql = "SELECT COUNT(TABLE_NAME) AS EXISTE
                            FROM INFORMATION_SCHEMA.TABLES
                            WHERE TABLE_SCHEMA = '".$objInfraBanco->getBanco()."'
                            AND TABLE_NAME = '".$strNomeTabela."'";
                break;
            
            case 'InfraSqlServer':
                
                $strSql = "SELECT COUNT(TABLE_NAME) AS EXISTE 
                            FROM INFORMATION_SCHEMA.TABLES
                            WHERE TABLE_CATALOG = '".$objInfraBanco->getBanco()."'
                            AND TABLE_NAME = '".$strNomeTabela."'";
                break;
                
            case 'InfraOracle':
                 $strSql = "SELECT 0 AS EXISTE";
                break;
        }
        
        $strSql = preg_replace('/\s+/', ' ', $strSql);
        $arrDados = $objInfraBanco->consultarSql($strSql);

        return (intval($arrDados[0]['EXISTE']) > 0) ? true : false;
    }
    
    public function isColuna($strNomeTabela = '', $strNomeColuna = ''){
              
        $objInfraBanco = $this->getObjInfraIBanco();
        $strTableDrive = get_parent_class($objInfraBanco);
            
        switch($strTableDrive) {

            case 'InfraMySql':
                $strSql = "SELECT COUNT(TABLE_NAME) AS EXISTE
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = '".$objInfraBanco->getBanco()."'
                            AND TABLE_NAME = '".$strNomeTabela."' 
                            AND COLUMN_NAME = '".$strNomeColuna."'";
                break;
            
            case 'InfraSqlServer':
                
                $strSql = "SELECT COUNT(COLUMN_NAME) AS EXISTE
                           FROM INFORMATION_SCHEMA.COLUMNS
                           WHERE TABLE_CATALOG = '".$objInfraBanco->getBanco()."'
                           AND TABLE_NAME = '".$strNomeTabela."' 
                           AND COLUMN_NAME = '".$strNomeColuna."'";
                break;
                
            case 'InfraOracle':
                 $strSql = "SELECT 0 AS EXISTE";
                break;
        }
        
        $strSql = preg_replace('/\s+/', ' ', $strSql);
        $arrDados = $objInfraBanco->consultarSql($strSql);

        return (intval($arrDados[0]['EXISTE']) > 0) ? true : false;
        
        
    }
    
    /**
     * Cria a estrutura da tabela no padr�o ANSI
     * 
     * @throws InfraException
     * @return PenMetaBD
     */
    public function criarTabela($arrSchema = array()){
        
        $strNomeTabela = $arrSchema['tabela'];
        
        
        if($this->isTabelaExiste($strNomeTabela)) {
            return $this;
        }
        
        $objInfraBanco = $this->getObjInfraIBanco();
        $arrColunas = array();
        $arrStrQuery = array();

        foreach($arrSchema['cols'] as $strNomeColuna => $arrColunaConfig) {
            
            list($strTipoDado, $strValorPadrao) = $arrColunaConfig;
            
            if($strValorPadrao != self::SNULLO && $strValorPadrao != self::NNULLO) {
                
                $arrStrQuery[] = $this->adicionarValorPadraoParaColuna($strNomeTabela, $strNomeColuna, $strValorPadrao, true);
                $strValorPadrao = self::NNULLO;
            }

            $arrColunas[] = $strNomeColuna.' '.$strTipoDado.' '.$strValorPadrao;
        }
        
        $objInfraBanco->executarSql('CREATE TABLE '.$strNomeTabela.' ('.implode(', ', $arrColunas).')');
        
        if(!empty($arrSchema['pk'])) {
            
           $this->adicionarChavePrimaria($strNomeTabela, 'pk_'.$strNomeTabela, $arrSchema['pk']); 
           
           if(count($arrSchema['pk']) > 1) {
               
               foreach($arrSchema['pk'] as $strPk) {
           
                    $objInfraBanco->executarSql('CREATE INDEX idx_'.$strNomeTabela.'_'.$strPk.' ON '.$strNomeTabela.'('.$strPk.')');
               }
           }
        }
        
        if(array_key_exists('uk', $arrSchema) && !empty($arrSchema['uk'])) {
            
            $this->adicionarChaveUnica($strNomeTabela, $arrSchema['uk']);
        }
        
        if(!empty($arrSchema['fks'])) {
            
            foreach($arrSchema['fks'] as $strTabelaOrigem => $array) {
                
                $strNomeFK = 'fk_'.$strNomeTabela.'_'.$strTabelaOrigem;
                $arrCamposOrigem = (array)array_shift($array);
                $arrCampos = $arrCamposOrigem;

                if(!empty($array)) {
                    $arrCampos = (array)array_shift($array);
                }

                $this->adicionarChaveEstrangeira($strNomeFK, $strNomeTabela, $arrCampos, $strTabelaOrigem, $arrCamposOrigem);   
            }
        }
        
        if(!empty($arrStrQuery)) {
            
            foreach($arrStrQuery as $strQuery) {    
                $objInfraBanco->executarSql($strQuery);
            }
        }
        
        return $this;
    }
    
    /**
     * Apagar a estrutura da tabela no banco de dados
     * 
     * @throws InfraException
     * @return PenMetaBD
     */
    public function removerTabela($strNomeTabela = ''){
        
        $this->getObjInfraIBanco()->executarSql('DROP TABLE '.$strNomeTabela);
        return $this;
    }
    
    public function adicionarChaveEstrangeira($strNomeFK, $strTabela, $arrCampos, $strTabelaOrigem, $arrCamposOrigem) {
        
        if(!$this->isChaveExiste($strTabela, $strNomeFK)) {
            parent::adicionarChaveEstrangeira($strNomeFK, $strTabela, $arrCampos, $strTabelaOrigem, $arrCamposOrigem);
        }
        return $this;
    }
    
    public function adicionarChavePrimaria($strTabela, $strNomePK, $arrCampos) {
        
        if(!$this->isChaveExiste($strTabela, $strNomePK)) {
            parent::adicionarChavePrimaria($strTabela, $strNomePK, $arrCampos);
        }
        return $this;
    }
    
    public function alterarColuna($strTabela, $strColuna, $strTipo, $strNull = '') {
        parent::alterarColuna($strTabela, $strColuna, $strTipo, $strNull);
        return $this;
    }
    
    public function excluirIndice($strTabela, $strIndex) {
        if($this->isChaveExiste($strTabela, $strFk)) {
            parent::excluirIndice($strTabela, $strIndex);
        }
        return $this;
    }
    
    public function excluirChaveEstrangeira($strTabela, $strFk) {
        if($this->isChaveExiste($strTabela, $strFk)) {
            parent::excluirChaveEstrangeira($strTabela, $strFk);
        }
        return $this;
    }
}