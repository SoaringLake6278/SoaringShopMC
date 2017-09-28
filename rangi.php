<html lang="pl">
<head>
<title>LinuxMC || Prawdopodobnie najlepszy serwer minecraft</title>
<link rel="Stylesheet" type="text/css" href="style.css" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<script src="SlickNav/jquery.slicknav.min.js"></script>
</head>
<body>

<div class="menu2">

<section class="nav">
<ul>
  <li><a href="index.php"><b>Home</b></a></li>
  <li><a href="rangi.php"><b>Rangi</b></a></li>
  <li><a href="regulamin.pdf"><b>Regulamin</b></a></li>
  <li><a href="administracja.php"><b>Administracja</b></a></li>
  <li><a href="sklep"><b>Sklep</b></a></li>
  <ul class="test">
    <li><a href="panel"><b>Panel Gracza</b></a></li>
  </ul>
</ul>
</section>


</div>
<div class="center">
<tekst>
<center><h1>Witaj na serwerze LinuxMC</h1></center><br>
<h2>Krótko o serwerze</h2>
Nasz serwer jest typu survival, naszym zadaniem jest zbieranie surowców i budowanie baz a także wspinanie się w rankingu PvP
</tekst>
<div class="ranga">
<tekst>
<h2>VIP</h2>
</tekst>


</div>
<div class="ranga">
<tekst>
<h2>Super VIP</h2>



</tekst>
</div>
</div>
<div class="info">
<tekst>
<h3><center>Status serwera</center></h3>
<?php
/*
╒════════════╕
 KONFIGURACJA
╘════════════╛
By Goukan/exevan
Aby dzialalo polaczenie z serwerem nalezy dodac do server.properties takie linijki:
enable-query=true
query.port=25565
*/
define( 'MQ_SERVER_ADDR', '192.168.8.4' ); //ip serwera
define( 'MQ_SERVER_PORT', 25020 ); //port query
define( 'MQ_TIMEOUT', 1 );

// Display everything in browser, because some people can't look in logs for errors
Error_Reporting(0);
Ini_Set( 'display_errors', true );

$Timer = MicroTime( true );
$Query = new MinecraftQuery( );
	
try
{
	$Query->Connect( MQ_SERVER_ADDR, MQ_SERVER_PORT, MQ_TIMEOUT );
}
catch( MinecraftQueryException $e )
{
	$Error = $e->getMessage( );
}
?>
<?php
class MinecraftQueryException extends Exception
{
}
class MinecraftQuery
{
	/*
	 * Class written by xPaw
	 */
	
	const STATISTIC = 0x00;
	const HANDSHAKE = 0x09;
	private $Socket;
	private $Players;
	private $Info;
	public function Connect( $Ip, $Port= 25565, $Timeout = 3 )
	{
		if( !is_int( $Timeout ) || $Timeout < 0 )
		{
			throw new InvalidArgumentException( 'Timeout must be an integer.' );
		}
		$this->Socket = @FSockOpen( 'udp://' . $Ip, (int)$Port, $ErrNo, $ErrStr, $Timeout );
		if( $ErrNo || $this->Socket === false )
		{
			throw new MinecraftQueryException( 'Could not create socket: ' . $ErrStr );
		}
		Stream_Set_Timeout( $this->Socket, $Timeout );
		Stream_Set_Blocking( $this->Socket, true );
		try
		{
			$Challenge = $this->GetChallenge( );	
			$this->GetStatus( $Challenge );
		}
		catch( MinecraftQueryException $e )
		{
			FClose( $this->Socket );	
			throw new MinecraftQueryException( $e->getMessage( ) );
		}
		FClose( $this->Socket );
	}
	public function GetInfo( )
	{
		return isset( $this->Info ) ? $this->Info : false;
	}
	public function GetPlayers( )
	{
		return isset( $this->Players ) ? $this->Players : false;
	}
	private function GetChallenge( )
	{
		$Data = $this->WriteData( self :: HANDSHAKE );
		if( $Data === false )
		{
			throw new MinecraftQueryException( "Failed to receive challenge." );
		}
		return Pack( 'N', $Data );
	}
	private function GetStatus( $Challenge )
	{
		$Data = $this->WriteData( self :: STATISTIC, $Challenge . Pack( 'c*', 0x00, 0x00, 0x00, 0x00 ) );
		if( !$Data )
		{
			throw new MinecraftQueryException( "Failed to receive status." );
		}
		$Last = "";
		$Info = Array( );
		$Data    = SubStr( $Data, 11 );
		$Data    = Explode( "\x00\x00\x01player_\x00\x00", $Data );
		$Players = SubStr( $Data[ 1 ], 0, -2 );
		$Data    = Explode( "\x00", $Data[ 0 ] );
		$Keys = Array(
			'hostname'   => 'HostName',
			'gametype'   => 'GameType',
			'version'    => 'Version',
			'plugins'    => 'Plugins',
			'map'        => 'Map',
			'numplayers' => 'Players',
			'maxplayers' => 'MaxPlayers',
			'hostport'   => 'HostPort',
			'hostip'     => 'HostIp'
		);
		foreach( $Data as $Key => $Value )
		{
			if( ~$Key & 1 )
			{
				if( !Array_Key_Exists( $Value, $Keys ) )
				{
					$Last = false;
					continue;
				}	
				$Last = $Keys[ $Value ];
				$Info[ $Last ] = "";
			}
			else if( $Last != false )
			{
				$Info[ $Last ] = $Value;
			}
		}
		$Info[ 'Players' ]    = IntVal( $Info[ 'Players' ] );
		$Info[ 'MaxPlayers' ] = IntVal( $Info[ 'MaxPlayers' ] );
		$Info[ 'HostPort' ]   = IntVal( $Info[ 'HostPort' ] );
		if( $Info[ 'Plugins' ] )
		{
			$Data = Explode( ": ", $Info[ 'Plugins' ], 2 );
			$Info[ 'RawPlugins' ] = $Info[ 'Plugins' ];
			$Info[ 'Software' ]   = $Data[ 0 ];
			if( Count( $Data ) == 2 )
			{
				$Info[ 'Plugins' ] = Explode( "; ", $Data[ 1 ] );
			}
		}
		else
		{
			$Info[ 'Software' ] = 'Vanilla';
		}
		$this->Info = $Info;
		
		if( $Players )
		{
			$this->Players = Explode( "\x00", $Players );
		}
	}
	private function WriteData( $Command, $Append = "" )
	{
		$Command = Pack( 'c*', 0xFE, 0xFD, $Command, 0x01, 0x02, 0x03, 0x04 ) . $Append;
		$Length  = StrLen( $Command );
		
		if( $Length !== FWrite( $this->Socket, $Command, $Length ) )
		{
			throw new MinecraftQueryException( "Failed to write on socket." );
		}
		$Data = FRead( $this->Socket, 2048 );
		if( $Data === false )
		{
			throw new MinecraftQueryException( "Failed to read from socket." );
		}
		if( StrLen( $Data ) < 5 || $Data[ 0 ] != $Command[ 2 ] )
		{
			return false;
		}
		return SubStr( $Data, 5 );
	}
}
	if( ( $Info = $Query->GetInfo( ) ) !== false )
	{
		foreach( $Info as $InfoKey => $InfoValue )
		{
			if( Is_Array( $InfoValue ) )
			{
				echo'';
			}
			else
			{	
				echo'';
			}
		}
	}

if(isset($Error))
{
	echo'Status: <font color="red">Offline</font><br>';
	echo'Nie udało połączyć się z serwerem.';
}
else
{
	echo'Status: <font color="green">Online</font><br>';
	echo'Graczy: '.$Info[ 'Players' ];
	echo'<br>IP: '.MQ_SERVER_ADDR;
	echo'<br>Wersja: '.$Info['Version'];
	echo'<br>Max Graczy: '.$Info['MaxPlayers'];
	echo'<br>Port: '.$Info['HostPort'];
	echo'<br>HostName: '.$Info['HostName'];
	echo'<br>Gracze online:';
	if(($Players = $Query->GetPlayers()) !== false)
	{
		$i = 1;
		foreach( $Players as $Player )
		{
			echo '<br>'.$i.'. '.htmlspecialchars( $Player );
			$i++;
		}
	}
	else
	{
		echo'<br>Brak graczy na serwerze.';
	}
}
?> 
</tekst>
</div>
</body>
</html>
