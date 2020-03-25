<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
use setasign\Fpdi\Fpdi;

require_once('fpdf/fpdf.php');
require_once('fpdi/vendor/autoload.php');

class FileGenerator
{

    public function generatePDF($source, $output, $request)
    {

        $pdf = new FPDI(); // Array sets the X, Y dimensions in mm  210,297

        $pdf->setSourceFile($source);

        // import a page
        $templateId = $pdf->importPage(1);

        $pdf->AddPage();
        // use the imported page and adjust the page size
        $pdf->useTemplate($templateId, ['adjustPageSize' => true]);

        $pdf->SetFont('Helvetica', '', 12);

        $pdf->SetMargins(0, 0, 0);

        $pdf->SetAutoPageBreak(false, 0);

        foreach ($request as $data) {

            $sign = glob($data['value']);

            if (count($sign) > 0) {
                $pdf->Image($data['value'], $this->convertPixelsInMM($data['posX']), $this->convertPixelsInMM($data['posY']), $this->convertPixelsInMM(103), $this->convertPixelsInMM(52)); // X start, Y start, X width, Y width in mm
                continue;
            }

            $pdf->SetXY($this->convertPixelsInMM($data['posX']), $this->convertPixelsInMM($data['posY']));

            $pdf->Write(8, $data['value']);
        }

        $pdf->Output("F", $output);

        return [
            'pdfH' => $pdf->GetPageHeight(),
            'pdfW' => $pdf->GetPageWidth()
        ];

    }

    protected function convertPixelsInMM($pixels)
    {
        return round(($pixels * 25.4) / 96);
    }

    public function createSignature($request)
    {

        $img = imagecreate($request['sign_width'], $request['sign_height']);
        $textbgcolor = imagecolorallocatealpha($img, 255, 255, 255, 0);
        $textcolor = imagecolorallocate($img, 0, 0, 0);

        if ($_POST['txt_input'] != '') {
            $txt = $_POST['txt_input'];
            $font = realpath('australia.ttf');
            imagettftext($img, 20, 0, 3, 40, $textcolor, $font, $txt);
            ob_start();
            $fileName = strtolower(str_replace(' ', '_', $txt)) . time() . '.png';
            $path = 'signatures/' . $fileName;
            imagepng($img, $path);

            //print_r($result);
        }
    }

    function convertToPDF($filePath, $fileName)
    {

        $pdf = new FPDF('P', 'mm');
        $pdf->AddPage();

        $txt = file_get_contents($filePath);

        $pdf->SetFont('Helvetica');

        $pdf->MultiCell(0, 5, $txt);

        $pdf->Ln();

        $path = 'converted_pdf/' . $fileName . '.pdf';

        $pdf->Output('F', $path);

        return $path;
    }

    public function createImageFromPDF($filePath, $fileName)
    {

        $image = new Imagick();
        $image->setResolution(300, 300);
        $image->setBackgroundColor('white');
        $image->readImage($filePath);
        $image->setGravity(Imagick::GRAVITY_CENTER);
        //$image->setOption('pdf:fit-to-page',true);
        $image->setImageFormat('jpg');
        $image->setImageCompression(imagick::COMPRESSION_JPEG);
        $image->setImageCompressionQuality(60);
        $image->scaleImage(1200, 1200, true);
        $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $imagePath = 'images_from_pdf/' . $fileName . '.jpg';
        $image->writeImage($imagePath);

        return $imagePath;
    }

}
$fileGenerator = new FileGenerator();

if (isset($_REQUEST['submit'])) {

    $targetdir = 'files/';

    $targetfile = $targetdir . $_FILES['file']['name'];

    $onlyFileName = substr($_FILES['file']['name'], 0, strrpos($_FILES['file']['name'], '.'));

    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetfile)) {
        $_SESSION['pathToPDF'] = $fileGenerator->convertToPDF($targetfile, $onlyFileName);
        $pathToImage = $fileGenerator->createImageFromPDF($_SESSION['pathToPDF'], $onlyFileName);
        echo 'Saved success';
    } else {
        echo 'Failed upload';
        //die('Failed upload');
    }
}

if (isset($_REQUEST['doc_data']) && isset($_SESSION['pathToPDF'])) {

    try {
        $docData = json_decode($_REQUEST['doc_data'], true);
        $fileGenerator->generatePDF($_SESSION['pathToPDF'], $_SESSION['pathToPDF'], $docData);
    } catch (Exception $exception) {
        echo 'Error';
    }

}


?>


<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<style>

    @font-face {
        font-family: australia;
        src: url('australia.ttf');
    }
    body{
        min-height: 500px;
        background-color: #e9e9e9;
        position: relative;
    }

    .print-text {
        font-family: australia;
        font-size: 28px;
        display: inline-block;
    }
    .wrapper{
        text-align: center;
    }

    .cursorText {
        position: fixed;
        z-index: 5;
    }
    #doc {
        margin: 0 auto;
        border: 1px solid #cccccc;

    }
    .form {
        text-align: center;
        margin: 20px 0;
    }

    span.removeElem {
        position: absolute;
        top: -16px;
        right: -10px;
        background: red;
        width: 15px;
        height: 15px;
        color: #fff;
        border-radius: 50%;
        text-align: center;
        cursor: pointer;
        z-index:1000;
    }
    img.sign {
        border: 1px solid #cccccc;
        pointer-events: none;
        user-select: none;
    }

    .btn-list {
        list-style-type: none;
        padding: 20px 5px 0 0;
        margin: 0;
        display: inline-block;
        border-right: 1px solid #cccccc;
    }

    button.fNB {
        width: 100%;
    }

    .btn-list > li {
        margin-top: 10px;
    }

    input.dynamic-field,
    #hide{
        font:inherit;
        margin:0;
        padding:0;
    }
    input.dynamic-field{
        border: 1px solid #888;
        min-width:60px;
    }
    #hide {
        border: 3px solid transparent;
        position: absolute;
        height: 0;
        overflow: hidden;
        white-space: pre;
    }

</style>

<body>

<form name="form" id="form" method="post" action="index.php"
      enctype="multipart/form-data" onsubmit="return validateForm();">
    <div class="form-row">
        <div>
            <label>Enter Text:</label>
            <input type="hidden" id="printWidth" name="sign_width" value="">
            <input type="hidden" id="printHeight" name="sign_height" value="">
            <input type="text" id="inputField" class="input-field" name="txt_input" maxlength="50">
            <input type="file" name="file" id="file">
            <div>
                <div class="print-text" id="printText"></div>
            </div>
        </div>
    </div>
    <div class="button-row">
        <input type="submit" id="submit" name="submit" value="Convert">
    </div>
</form>

<ul class="btn-list">
    <li>
        <button class="fNB" data-value="signature">Signature</button>
    </li>
    <li>
        <button class="fNB" data-value="full_name">Full Name</button>
    </li>
    <li>
        <button class="fNB" data-value="field">Text field</button>
    </li>
</ul>

<div id="wrapper">
    <?php if (isset($pathToImage)) :?>
    <div id="doc" style="background: url(<?php echo $pathToImage; ?>); width: 794px; height: 1115px;"></div>
    <?php endif; ?>
    <form action="index.php" method="post" class="form" id="docForm">
        <input type="hidden" id="docData" name="doc_data">
        <input type="submit" id="docSub" name="doc_sub" value="Submit">
    </form>
</div>

<script>

    let input = document.getElementById('inputField'),
        printText = document.getElementById('printText'),
        printW = document.getElementById('printWidth'),
        printH = document.getElementById('printHeight');

    input.addEventListener('keyup', function (e) {
        printText.innerText = e.currentTarget.value;
        printW.value = printText.offsetWidth;
        printH.value = printText.offsetHeight;
    });

    document.addEventListener('DOMContentLoaded', function() {

        let curTxt = null,
            input = null,
            remove = null,
            curTxtLen = null,
            clickBtns = document.querySelectorAll('.fNB'),
            doc = document.getElementById('doc'),
            selected = false,
            key = 0,
            span = null;

        let docForm = document.getElementById('docForm');
        let subDoc = document.getElementById('docSub');

        subDoc.addEventListener('click', function (e) {
            e.preventDefault();
            let docFields = document.getElementsByClassName('cursorText'),
                docData = [],
                docInput = document.getElementById('docData');

            Array.prototype.forEach.call(docFields, child => {
                docData.push({
                    posX: child.getAttribute('posx'),
                    posY: child.getAttribute('posy'),
                    value: child.lastChild.previousSibling.value
                });
            });

            docInput.value = JSON.stringify(docData);

            docForm.submit();

        });

        function createElem(type) {

            curTxt = document.createElement('div');
            curTxt.className = "cursorText";
            curTxt.setAttribute("data-key", key++);
            curTxt.setAttribute("data-set", false);

            span = document.createElement('span');
            span.id = 'hide';

            switch (type) {
                case 'signature' :
                    input = document.createElement('img');
                    input.src = 'signatures/jhon_doe1581516984.png';
                    input.value = 'signatures/jhon_doe1581516984.png';
                    input.className = 'sign';
                    break;
                case 'full_name' :
                    input = document.createElement('input');
                    input.type = "text";
                    input.value = 'Jhon Doe';
                    input.className = 'dynamic-field';
                    curTxt.appendChild(span);
                    break;
                case 'field' :
                    input = document.createElement('input');
                    input.type = "text";
                    input.className = 'dynamic-field';
                    curTxt.appendChild(span);
                    break;
            }

            remove = document.createElement('span');
            remove.className = "removeElem";
            remove.innerHTML = "&times";

            curTxt.appendChild(input);
            curTxt.appendChild(remove);

            curTxtLen = [curTxt.offsetWidth,curTxt.offsetHeight];
        }

        function resizeInput(e) {
            let event = e;
            if (e.target) {
                event = e.target
            }
            event.previousSibling.textContent = event.value;
            event.style.width = event.previousSibling.offsetWidth + 22 + "px";
        }


        function moveCursor(e) {
            if(!e){e = window.event;}
            curTxt.style.left = e.clientX-curTxtLen[0] + 8 + 'px';
            curTxt.style.top = e.clientY-curTxtLen[1] + 8 + 'px';
        }

        clickBtns.forEach(function (btn) {

            btn.addEventListener('click', function (e) {
                if (!selected) {
                    selected = true;
                    document.body.onmousemove = moveCursor;
                    createElem(this.getAttribute('data-value'));
                    curTxt.style.left = e.clientX - curTxtLen[0] + 8 + 'px';
                    curTxt.style.top = e.clientY - curTxtLen[1] + 8 + 'px';
                    doc.appendChild(curTxt);
                    document.body.addEventListener('click', removeFromWrap);
                    doc.addEventListener('click', setElem);
                    resizeInput(input);
                }

            });

        });

        function removeFromWrap(e) {

            if (e.target.id == 'wrapper') {
                let rFW = document.querySelectorAll(".cursorText:last-child");
                if (rFW[0].getAttribute('data-set') == 'false') {
                    rFW[0].remove();
                    selected = false;
                    doc.removeEventListener('click', setElem);
                    document.body.removeEventListener('click', removeFromWrap);
                }
            }
        }

        function removeInput() {
            let cT = this.parentElement.getAttribute('data-key'),
                item = document.querySelector(`[data-key='${cT}']`);
            item.remove();
        }


        function dragInput(event) {

            let el = this,
                shiftX = event.clientX - el.getBoundingClientRect().left,
                shiftY = event.clientY - el.getBoundingClientRect().top;

            moveAt(event);

            function moveAt(event) {
                el.style.left = event.clientX - shiftX - 8 + 'px';
                el.style.top = (event.clientY + document.body.scrollTop) - shiftY - 8 + 'px';
            }

            function onMouseMove(event) {
                moveAt(event);
            }

            document.addEventListener('mousemove', onMouseMove);

            el.onmouseup = function() {

                let leftX = event.clientX - event.layerX,
                    bottomY = event.clientY - event.layerY,
                    parentRect = el.parentNode.getBoundingClientRect(),
                    elRect = el.getBoundingClientRect();


                if (elRect.right > parentRect.right) {
                    el.style.left = leftX - (elRect.right - parentRect.right) - 9 + 'px';
                }
                if (elRect.bottom > parentRect.bottom) {
                    el.style.top = bottomY - (elRect.bottom - (parentRect.bottom + document.body.scrollTop)) - 9 + 'px';
                }
                if (elRect.left < parentRect.left) {
                    el.style.left = leftX + (parentRect.left - elRect.left) - 7 + 'px';
                }
                if (elRect.top < parentRect.top) {
                    el.style.top = bottomY + (parentRect.top - elRect.top) - 7 + 'px';
                }

                el.setAttribute('posX', el.getBoundingClientRect().left - el.parentNode.getBoundingClientRect().left);
                el.setAttribute('posY', el.getBoundingClientRect().top - el.parentNode.getBoundingClientRect().top);

                document.removeEventListener('mousemove', onMouseMove);
                el.onmouseup = null;
            };

        }

        function placeDiv(x_pos, y_pos) {
            let d = document.querySelectorAll(".cursorText:last-child");
            d[0].style.position = "absolute";
            d[0].style.left = x_pos + 'px';
            d[0].style.top = y_pos + 'px';
            d[0].setAttribute('data-set', true);
        }

        function setElem(e) {

            let clientX = e.clientX,
                clientY = e.clientY + document.body.scrollTop,
                rX = this.offsetWidth - e.offsetX,
                rY = this.offsetHeight - e.offsetY,
                inputWidth = this.lastChild.offsetWidth + 10,
                inputHeight = this.lastChild.offsetHeight + 10,
                pX = e.target.lastChild.offsetLeft - e.target.offsetLeft,
                pY = e.target.lastChild.offsetTop - e.target.offsetTop + document.body.scrollTop;

            if (rX < inputWidth) {
                let d = inputWidth - rX;
                clientX = clientX - d;
                pX = pX - d - 10;
            }

            if (rY < inputHeight) {
                let d1 = inputHeight - rY;
                clientY = clientY - d1;
                pY = pY - d1 - 10;
            }

            let cT = document.querySelectorAll(".cursorText");

            cT.forEach(function(el) {
                el.onmousedown = dragInput.bind(el);
                el.addEventListener("input", resizeInput);
            });

            let removeElem = document.querySelectorAll('.removeElem');

            removeElem.forEach(function(rEl) {
                rEl.onmousedown = removeInput.bind(rEl);
            });

            this.lastElementChild.setAttribute('posX', pX);
            this.lastElementChild.setAttribute('posY', pY);

            placeDiv(clientX, clientY);
            document.body.onmousemove = null;
            this.removeEventListener('click', setElem);
            selected = false;
        }

    });

</script>

</body>
</html>


