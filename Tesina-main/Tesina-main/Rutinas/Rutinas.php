<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rutinas</title>
    <link rel="stylesheet" href="../Css/Standard.css">
</head>
<body>
    <?php require '../Bl.php';?>
    <?php include '../1Rm.html'; ?>
    <script src="../Send-Recieve/Send-Recieve.js"></script>
    <script>
        var url = "../Ejercicios/ListaEjercicios.php";

        function Deset(id) {
            Ejs = document.getElementsByClassName("Id");
            a = Array.from(Ejs);
            aStr = [] 

            a.forEach((a, i) => {
                aStr.push(Ejs[i].value);
                
            });
            
            Send(id + "-" + aStr, "remove=", true)
        }
    </script>
        <label for="">aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa</label>
        <button onClick="ShowBtn()" Id='EditBtn'>editar</button>
    <?php

        error_reporting(E_ALL);
        ini_set('display_errors', 1);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['wId'])) {
                $_SESSION['wId'] = $_POST['wId'];
            };
        };


        $_SESSION['User'] = 1;
        $Con = conect();

        $User = $_SESSION['User'];

        //Get id
            $Id = $_SESSION['wId'];

            //Fetch info
            $Sql = "SELECT * FROM Works WHERE id = $Id";
            $temp = r($Con, $Sql);
            
            //Crea un div por cada ejercicio
            while ($row = mysqli_fetch_assoc($temp)) {
                //Datos (Str) -> array
                $Ejercicios = implode(",", array_map('intval', explode(", ", $row['Ejercicios'])));
                $Sets = array_map('intval', explode(", ", $row['Sets'])); 
                $Reps = array_map('intval', explode(", ", $row['Reps'])); 

                $r = r($Con, c("Ejercicios", "id, Name, mu", "WHERE id in ($Ejercicios)"));
     
                if ($r->num_rows > 0) { 
                    while ($Row = $r->fetch_assoc()) {
                        //$NE [ubicacion de id actual] = $id
                        $NE[array_search($Row['id'], explode(",", $Ejercicios))] = $Row;
                    }

                    echo "<div Class='Rutina' id= 'Rutina'>";

                    for ($i=0; $i < count(array_map('intval', explode(",", $Ejercicios))); $i++) { 
                        

                        $Temp = $NE[$i]['id'];
                        //ESTE
                        echo "<div class='Ej_Rutina' id='Ejr-$i'>
                            <a href=''>{$NE[$i]['Name']}</a>
                            <input type = 'hidden' value = '$Temp' Class='Id'>
                            <input type = 'number' placeholder ='$Sets[$i]' id= 'Sets-$i'></input>
                            <input type = 'number' placeholder ='$Reps[$i]' id= 'Reps-$i'></input>
                            <button Onclick='Deset($Temp)' name='Del'>borrar</button>";
                            if ( count(array_map('intval', explode(",", $Ejercicios))) > 1) {
                                    if ($i > 0 && $i < count(array_map('intval', explode(",", $Ejercicios))) - 1) {
                                    echo "<button onclick='Order($i, 0)' id='UP $i' name = 'UP'>↑</button>
                                    <button onclick='Order($i, 1)' id='DOWN $i' name = 'DOWN'>↓</button>";
                                } else{
                                    if ($i == 0) {
                                        echo "<button onclick='Order($i, 1)' id='DOWN $i' name='DOWN'>↓</button>";
                                    }else {
                                        echo "<button onclick='Order($i, 0)' id='UP $i' name='UP'>↑</button>";
                                    };
                                } 
                            } else {
                                //Pass
                            }
                            
                            
                            echo "</div>";
                    }

                    echo "<Button Onclick = 'Ne()' name = 'Ne'> + </Button>
                    </div>";
                } else{
                        echo "<div Class='Rutina'>
                            <Button Onclick = 'Ne()' name = 'Ne'> + </Button>
                        </div>";
                    }




            }



    ?>


    <script>

        var url = "../Ejercicios/ListaEjercicios.php";
        var condition = "";

        function Ne() {

            const SelectDiv = document.createElement('div'); //Div principal
            
            const SelectHead = document.createElement('div');   //Div Header
            
            const SelectSide = document.createElement('div');   //Side
            const SelectChabon = document.createElement('input');   //Chabon q marca los musculos

            const SelectList = document.createElement('div');   //List

            Send("", "Conditions=", false);

            //GET Data
            Get (url, function(Data){   //Crea una funcion con un argumento (Data) - Ver Send-Recieve.js

                const List = Object.entries(Data.Num).map(([id, Name]) => ({ id, Name })); //Obj -> Array

                const Info = [];
                for (let i = 0; i < List.length; i++) {
                    Info [i] = List[i].Name.split(", "); //"Name", "ISSET"
                    console.log(Info);
                    
                };

                //Clases (Css)
                SelectDiv.className = "Selector";

                SelectHead.className = "SeHe";
                SelectSide.className = "SeSi";
                SelectList.className = "SeLi";

                document.body.appendChild(SelectDiv);

                //Separacion inderior
                const tempdiv = document.createElement('div');
                tempdiv.className = "Temp";

                SelectDiv.append(SelectHead, tempdiv);
                tempdiv.append(SelectSide, SelectList);

                

               

                const Search = document.createElement('input');
                SelectHead.append (Search);

                function Create(List, Info) { //Crea la lista de ejercicios
                    //Para cada ejercicio
                    List.forEach(function(List, i) {

                        const EDiv = document.createElement('div'); //div de ejercicio
                        const Ej = document.createElement('button'); //Boton de ejercicio

                        EDiv.className = "EDiv";

                        Ej.textContent = Info[i][0];
                        
                        //Si ya esta desactiva el boton
                        if (Info[i][1] == "false") {
                            Ej.onclick = function(){
                                Send(List.id, "a=", true);
                            };

                            Ej.className = "Active"
                        } else {
                            Ej.className = "Inactive"
                            Ej.onclick = ""
                        }
                    
                    
                    
                        SelectList.appendChild(EDiv);
                        EDiv.appendChild(Ej)
                    
                    }); 
                }

                let Debounce;
                //Detecta el input del usuario
                window.addEventListener("keypress", function (event) {

                    //timer anti-spam
                    clearTimeout(Debounce);
                    Debounce = setTimeout(() => {

                    //similar al input
                    if (condition == "") {
                        Send("WHERE Name like '%"+ Search.value + "%'", "Conditions=", false);
                    } else{
                        Send("WHERE Name like '%"+ Search.value + "%' "+condition, "Conditions=", false);
                    }
                    

                    if (Search.contains(event.target)){

                        //borrar todo
                        Delete = SelectList.getElementsByTagName('div');
                        while (Delete.length > 0) {
                            Delete[0].remove();
                        }


                        Get(url, function(Data) { //ReRecibe toda la data
                            const List = Object.entries(Data.Num).map(([id, Name]) => ({ id, Name })); //Obj -> Array

                            const Info = [];
                            for (let i = 0; i < List.length; i++) {
                                Info [i] = List[i].Name.split(", "); //"Name", "ISSET"
                            };

                            Create(List, Info)
                        
                        })
                    }
                    }, 100);

                })

            let type = []
            for (let i = 0; i < 2; i++) {
                const Label = document.createElement('label');
                const SelectFilter = document.createElement('input');  //Filters

                type[0] = "Libre";
                type[1] = "Cable";
                
                Label.textContent = type[i];


                SelectFilter.type = "checkbox";
                SelectFilter.id = "Select " + i;
                SelectFilter.value = type[i];


                SelectHead.append(Label, SelectFilter);
            }

            Select0 = document.getElementById("Select 0");
            Select1 = document.getElementById("Select 1");
            
             //Salir y checkbox
                window.addEventListener("click", function(event){
                        if (!SelectDiv.contains(event.target)) {
                            SelectDiv.remove();
                        }

                        if (Select0.contains(event.target)) {
                            Select1.checked = false;
                            condition = "AND Type = 'Libre'";
                            Send("WHERE Name like '%"+ Search.value + "%' "+condition, "Conditions=", false);
                            //borrar todo
                            Delete = SelectList.getElementsByTagName('div');
                            while (Delete.length > 0) {
                                Delete[0].remove();
                            }
                            Get(url, function(Data) { //ReRecibe toda la data
                            const List = Object.entries(Data.Num).map(([id, Name]) => ({ id, Name })); //Obj -> Array

                            const Info = [];
                            for (let i = 0; i < List.length; i++) {
                                Info [i] = List[i].Name.split(", "); //"Name", "ISSET"
                            }
                        Create(List, Info)
                        });
                            
                        }

                        if (Select1.contains(event.target)) {
                            Select0.checked = false;
                            condition = "AND Type = 'Cable'";
                            Send("WHERE Name like '%"+ Search.value + "%' "+condition, "Conditions=", false);
                            //borrar todo
                            Delete = SelectList.getElementsByTagName('div');
                            while (Delete.length > 0) {
                                Delete[0].remove();
                            }
                            Get(url, function(Data) { //ReRecibe toda la data
                            const List = Object.entries(Data.Num).map(([id, Name]) => ({ id, Name })); //Obj -> Array

                            const Info = [];
                            for (let i = 0; i < List.length; i++) {
                                Info [i] = List[i].Name.split(", "); //"Name", "ISSET"
                            }
                            Create(List, Info)
                        });
                            
                        }

                        if (Select0.contains(event.target) || Select1.contains(event.target)) {
                            if (Select0.checked == false && Select1.checked == false) {
                                condition = "";
                                Send("WHERE Name like '%"+ Search.value + "%'", "Conditions=", false);
                                //borrar todo
                            Delete = SelectList.getElementsByTagName('div');
                            while (Delete.length > 0) {
                                Delete[0].remove();
                            }
                            
                            
                        }
                    }
                })
            Create(List, Info)
        })
    }

    async function Order(id, dir) {
    Send(id + "," + dir, "Ch=", false);

    setTimeout(() => {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, "text/html");

                const nuevaRutina = doc.getElementById("Rutina");
                document.getElementById("Rutina").innerHTML = nuevaRutina.innerHTML;
            });
    }, 100);
}

            let ola = document.getElementById("Sets-0")
            if (ola !== null) {
                let Active = document.activeElement;
                window.addEventListener('click', function(event) {

                if (Active.value >= 50){
                Active.value = 50 
                }
    //change
                if (!document.activeElement.contains(event.target) && Active.tagName == ola.tagName) {   
                       Send (Active.value + "," + Active.id, "NewSR=", false)
                       Active.placeholder = Active.value
                }   
                Active = document.activeElement; 

                if (Active.tagName == ola.tagName) {
                    if (Active.value >= 50){
                        Active.value = 50
                    } 

                Active.addEventListener('keydown', function(event){
                        Send (Active.value + "," + Active.id, "NewSR=", false)
                        Active.placeholder = Active.value

                })
              }    
        })



            }
        function HideBtn() {
            buttons = [document.getElementsByName("DOWN"), document.getElementsByName("UP"), document.getElementsByName("Del"), document.getElementsByName("Ne")];
            for (i = 0; i < buttons.length; i++) {
                for (ind = 0; ind < buttons[i].length; ind++) {
                    buttons[i][ind].style.visibility = 'hidden'
                }
            }
            document.getElementById("EditBtn").textContent = "Editar"
            document.getElementById("EditBtn").onclick = ShowBtn;
        }
        HideBtn()

        function ShowBtn() {
            buttons = [document.getElementsByName("DOWN"), document.getElementsByName("UP"), document.getElementsByName("Del"), document.getElementsByName("Ne")];
            console.log(buttons);
            for (i = 0; i < buttons.length; i++) {
                for (ind = 0; ind < buttons[i].length; ind++) {
                    buttons[i][ind].style.visibility = 'visible';
                }
            }
            document.getElementById("EditBtn").textContent = "V"
            document.getElementById("EditBtn").onclick = HideBtn;
        }
        

    </script>
</body>
</html>
