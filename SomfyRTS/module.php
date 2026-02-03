<?php

declare(strict_types=1);

// General functions
require_once __DIR__ . '/../libs/_traits.php';

/**
 * CLASS Somfy RTS
 */
class SomfyRTS extends IPSModuleStrict
{
    // Helper Traits
    use DebugHelper;
    use FormatHelper;
    use TemplateHelper;
    use VariableHelper;

    /**
     * @var int Minimum valid IPS Object ID for variables
     */
    private const IPS_MIN_ID = 10000;

    // Data flow IDs
    /**
     * @var string GUID of the Simple I/O instance
     */
    private const GUID_SIMPLE_IO = '{6DC3D946-0D31-450F-A8C6-C42DB8D7D4F1}';
    /**
     * @var string GUID of the Simple TX instance
     */
    private const GUID_SIMPLE_TX = '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}';  // from module to port
    // private const GUID_SIMPLE_RX = '{018EF6B5-AB94-40C6-AA53-46943E824ACF}';  // from port to module

    /**
     * @var array<string,mixed> Presentation (Enumeration) for Awning
     */
    private const RTY_PRESENTATION_AWNING = [
        'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
        'OPTIONS'      => '[{"Caption":"My/Stop","Color":-1,"IconActive":true,"IconValue":"square-m","Value":0},{"Caption":"In","Color":-1,"IconActive":true,"IconValue":"square-arrow-left","Value":1},{"Caption":"Out","Color":-1,"IconActive":true,"IconValue":"square-arrow-right","Value":3}]',
        'LAYOUT'       => 0,
        'ICON'         => '',
        'DISPLAY'      => 1,
    ];

    /**
     * @var array<string,mixed> Presentation (Enumeration) for Valance
     */
    private const RTY_PRESENTATION_VALANCE = [
        'PRESENTATION' => VARIABLE_PRESENTATION_ENUMERATION,
        'OPTIONS'      => '[{"Caption":"My/Stop","Color":-1,"IconActive":true,"IconValue":"square-m","Value":0},{"Caption":"Up","Color":-1,"IconActive":true,"IconValue":"square-arrow-up","Value":1},{"Caption":"Down","Color":-1,"IconActive":true,"IconValue":"square-arrow-down","Value":3}]',
        'LAYOUT'       => 0,
        'ICON'         => '',
        'DISPLAY'      => 1,
    ];

    /**
     * @var string Prefix for Somfy RTS messages
     */
    private const RTY_PREFIX = "\x0C\x1A";

    /**
     * @var string Suffix for Somfy RTS messages
     */
    private const RTY_SUFFIX = "\x00\x00\x00\x00";

    /**
     * In contrast to Construct, this function is called only once when creating the instance and starting IP-Symcon.
     * Therefore, status variables and module properties which the module requires permanently should be created here.
     *
     * @return void
     */
    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        // Unit
        $this->RegisterPropertyInteger('UnitType', 0);
        $this->RegisterPropertyString('UnitID', '000000');
        $this->RegisterPropertyString('UnitCode', '01');
        // Visu
        $this->RegisterPropertyInteger('VisuType', 0);
        $this->RegisterPropertyString('VisuPosition', 'right');
        $this->RegisterPropertyInteger('VisuVariable', 1);
        $this->RegisterPropertyInteger('VisuInColor', -1);
        $this->RegisterPropertyInteger('VisuOutColor', -1);

        if ((float) IPS_GetKernelVersion() < 8.2) {
            // I/O Instance
            $this->RequireParent(self::GUID_SIMPLE_IO);
        }

        // Set visualization type to 1, as we want to offer HTML
        $this->SetVisualizationType(1);
    }

    /**
     * This function is called when deleting the instance during operation and when updating via "Module Control".
     * The function is not called when exiting IP-Symcon.
     *
     * @return void
     */
    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    /**
     * The content can be overwritten in order to transfer a self-created configuration page.
     * This way, content can be generated dynamically.
     * In this case, the "form.json" on the file system is completely ignored.
     *
     * @return string Content of the configuration page.
     */
    public function GetConfigurationForm(): string
    {
        // Get Form
        $form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);

        // Extract Version
        $ins = IPS_GetInstance($this->InstanceID);
        $mod = IPS_GetModule($ins['ModuleInfo']['ModuleID']);
        $lib = IPS_GetLibrary($mod['LibraryID']);
        $form['actions'][1]['items'][2]['caption'] = sprintf('v%s.%d', $lib['Version'], $lib['Build']);

        // Debug output
        // $this->LogDebug(__FUNCTION__, $form);
        return json_encode($form);
    }

    /**
     * Is executed when "Apply" is pressed on the configuration page and immediately after the instance has been created.
     *
     * @return void
     */
    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();

        // Unregister reference
        foreach ($this->GetReferenceList() as $id) {
            $this->UnregisterReference($id);
        }
        // Unregister all messages
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                $this->UnregisterMessage($senderID, $message);
            }
        }

        // Setup infos
        $type = $this->ReadPropertyInteger('VisuType');
        $present = ($type == 0) ? self::RTY_PRESENTATION_AWNING : self::RTY_PRESENTATION_VALANCE;
        $present = $this->TranslateCaptions($present);
        $this->LogDebug(__FUNCTION__, $present);

        // Maintain variables
        $this->MaintainVariable('Remote', $this->Translate('Remote Control'), 1, $present, 1, true);
        $this->MaintainAction('Remote', true);

        $variable = $this->ReadPropertyInteger('VisuVariable');
        if (IPS_VariableExists($variable)) {
            $this->RegisterReference($variable);
            $this->RegisterMessage($variable, VM_UPDATE);
        }
        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * Is called when, for example, a button is clicked in the visualization.
     *
     * @param string $ident Ident of the variable
     * @param mixed $value The value to be set
     * @return void
     */
    public function RequestAction(string $ident, mixed $value): void
    {
        // Debug output
        $this->LogDebug(__FUNCTION__, $ident . ' => ' . $value);
        switch ($ident) {
            case 'Remote':
                $this->HandleCommand($value);
                break;
        }
        // Send a complete update message to the display, as parameters may have changed
        $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
    }

    /**
     * This function is called by IP-Symcon and processes sent data and, if necessary, forwards it to
     * all child instances. Data can be sent using the SendDataToChildren function.
     *
     * Response = ACK,
     *  Raw data = 0402010100
     *
     * @param string $json Data package in JSON format
     * @return string Optional response to the parent instance
     */
    public function ReceiveData(string $json): string
    {
        $data = json_decode($json);
        $this->LogDebug(__FUNCTION__, utf8_decode($data->Buffer));
        return '';
    }

    /**
     * If the HTML-SDK is to be used, this function must be overwritten in order to return the HTML content.
     *
     * @return string Initial display of a representation via HTML SDK
     */
    public function GetVisualizationTile(): string
    {
        // Add a script to set the values when loading, analogous to changes at runtime
        // Although the return from GetFullUpdateMessage is already JSON-encoded, json_encode is still executed a second time
        // This adds quotation marks to the string and any quotation marks within it are escaped correctly
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        // Add static HTML from file
        $module = file_get_contents(__DIR__ . '/module.html');
        // Return everything
        // Important: $initialHandling at the end, as the handleMessage function is only defined in the HTML
        return $module . $initialHandling;
    }

    /**
     * The content of the function can be overwritten in order to carry out own reactions to certain messages.
     * The function is only called for registered MessageIDs/SenderIDs combinations.
     *
     * data[0] = new value
     * data[1] = value changed?
     * data[2] = old value
     * data[3] = timestamp.
     *
     * @param int   $timestamp Continuous counter timestamp
     * @param int   $sender    Sender ID
     * @param int   $message   ID of the message
     * @param array{0:mixed,1:bool,2:mixed,3:int} $data Data of the message
     * @return void
     */
    public function MessageSink(int $timestamp, int $sender, int $message, array $data): void
    {
        // Debug
        // $this->LogDebug(__FUNCTION__, 'SenderId: ' . $sender . ' Data: ' . $this->DebugPrint($data), 0);
        // React to updates
        if ($message == VM_UPDATE) {
            // only if values changed!
            if ($data[1] == true) {
                // Dark Mode activation
                if ($this->ReadPropertyInteger('VisuVariable') == $sender) {
                    // Parts of the HTML display the new state with
                    //$this->UpdateVisualizationValue(json_encode(['status' => $data[0]]));
                    // Send a complete update message to the display, as parameters may have changed
                    $this->UpdateVisualizationValue($this->GetFullUpdateMessage());
                }
            }
        }
    }

    /**
     * Send data to I/O interface
     *
     * 0C1A 00 01 010203 01 03 00000000
     * ---- -- -- ------ -- -- --------
     * |    |  |  |      |  |  |======= 8 x zero
     * |    |  |  |      |  |========== command: STOP(00), UP(01), DOWN(03)
     * |    |  |  |      |============= unit code 00 to FF
     * |    |  |  |==================== unit id (000001 to FFFFFF)
     * |    |  |======================= sequence number (01)
     * |    |========================== subtype (00 = Somfy RTS(RFY), 01 = RFY-EXT, 03 = ASA)
     * |=============================== prefix (0C1A)
     *
     * @param string $text sequence of unit and command
     * @return string Result of the parent call
     */
    private function SendData(string $text): string
    {
        // Serial Port
        $simple['DataID'] = self::GUID_SIMPLE_TX;
        $simple['Buffer'] = bin2hex(self::RTY_PREFIX . $text . self::RTY_SUFFIX);
        $json = json_encode($simple, JSON_UNESCAPED_SLASHES);
        $this->LogDebug(__FUNCTION__, $json);
        return @$this->SendDataToParent($json);
    }

    /**
     * Transform the passed command to a full sequence and send the data
     *
     * @param int $command Command to execute
     * @return void
     */
    private function HandleCommand(int $command): void
    {
        $type = $this->ReadPropertyInteger('UnitType');
        $id = $this->ReadPropertyString('UnitID');
        $code = $this->ReadPropertyString('UnitCode');

        // $type → chr() with leading zero
        $output = chr($type); // corresponds chr(0x00)
        // $sequence → chr() fixed one with leading zero
        $output .= chr(1); // corresponds chr(0x01)
        // $code → 6 digits in 3 bytes
        $output .= hex2bin($id);
        // $id → 2-digit in 1 byte
        $output .= hex2bin($code);
        // $command → chr() with leading zero
        $output .= chr($command);
        // output as hex string for checking
        $this->LogDebug(__FUNCTION__, bin2hex($output));
        // send data
        $this->SendData($output);
        // set status variable
        $this->SetValueInteger('Remote', $command);
    }

    /**
     * Generate a message that updates all elements in the HTML display.
     *
     * @return string JSON encoded message information
     */
    private function GetFullUpdateMessage(): string
    {
        // dataset variable
        $remote = match ($this->GetValue('Remote')) {
            0       => 'half',
            1       => 'none',
            3       => 'full',
            default => 'unknown', // handle all other cases
        };
        $status = 'in';
        $vid = $this->ReadPropertyInteger('VisuVariable');
        if ($vid >= self::IPS_MIN_ID) {
            if (!GetValue($vid)) {
                $status = 'out';
            }
        }
        $type = $this->ReadPropertyInteger('VisuType');
        $position = $this->ReadPropertyString('VisuPosition');
        $in = $this->GetColorFormatted($this->ReadPropertyInteger('VisuInColor'));
        $out = $this->GetColorFormatted($this->ReadPropertyInteger('VisuOutColor'));

        // Data
        $result = [
            'status'    => ($status == 'in' ? $in : $out),
            'type'      => ($type == 0 ? 'awning' : 'valance'),
            'align'     => ($position),
            'remote'    => ($remote),
        ];
        //$this->LogDebug(__FUNCTION__, $result);
        return json_encode($result);
    }
}
