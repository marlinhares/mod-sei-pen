<?php

class PaginaLogin extends PaginaTeste
{
    public function __construct($test)
    {
	    parent::__construct($test);

	$this->test->waitUntil(function($testCase) {
	    $this->usuarioInput = $test->byId('txtUsuario');	
	    echo 'a';
            return true;
        }, 30);
	
        $this->usuarioInput = $test->byId('txtUsuario');
        $this->passwordInput = $test->byId('pwdSenha');
        $this->loginButton = $test->byId('sbmLogin');
    }

    public function usuario($value)
    {
        if(isset($value))
            $this->usuarioInput->value($value);

        return $this->usuarioInput->value();
    }

    public function senha($value)
    {
        if(isset($value))
            $this->passwordInput->value($value);

        return $this->passwordInput->value();
    }

    public function orgao()
    {
        return $this->test->byId('divInfraBarraSuperior')->text();
    }

    public function submit()
    {
        $this->loginButton->click();
        return $this->test;
    }

    public static function executarAutenticacao($test, $usuario="teste", $senha="teste")
    {
        $paginaLogin = new PaginaLogin($test);
        $paginaLogin->usuario($usuario);
        $paginaLogin->senha($senha);
        $paginaLogin->submit();
    }
}
