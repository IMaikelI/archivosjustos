<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rutinas</title>
    <link rel="stylesheet" href="../Css/Standard.css">
</head>
<body>
    <?php include '../Bl.php'; ?>
    <?php include '../1Rm.html'; ?>
    <div id="Lista" class='lista'>
        <?php require 'Lista.php'; ?>
    </div>

    <script src="../Send-Recieve/Send-Recieve.js"></script>
    <script>
        var url = "ListaRutinas.php";

        function NewRoutine(){

            //Busca los objetos
            const routines = document.getElementById("+Rutina");
            const plus = document.getElementById("+");

            //crea un input y le da propiedades
            const temp = document.createElement('Input');
            temp.id = "temp";
            temp.type = "text";

            routines.insertBefore(temp, plus);
            temp.focus ();
            plus.remove();

                //Delay obligatorio 
                setTimeout(() => {

                    //Detectar click
                    window.addEventListener("click", function(event){

                        //Detectar click fuera de input
                        if (!temp.contains(event.target)) {

                            const deleteinput = document.getElementById('temp');
                            deleteinput.remove()

                            const NewPlus = document.createElement('button')

                            NewPlus.id = '+';
                            NewPlus.textContent = '+';
                            NewPlus.ClassList = '+';
                            NewPlus.onclick = NewRoutine

                            routines.appendChild(NewPlus);

                        }
                    }, {once : true}); 
                },1 );

            //Enter event
            window.addEventListener("keypress", function (event) {
              if (event.key === "Enter") {

                Send(temp.value, "Nr=", true)
                
                //Borra el input
                temp.remove();
            }
              ;
            }, true)
        };

        function edit(id){
            const Sub = document.getElementById("Edit " + id);
            
            const Parent = Sub.parentElement;
            const Cancel = document.getElementById("Cancel " + id); 

            const Edit = document.createElement('input');                       

            Edit.placeholder = Sub.textContent;
            Edit.focus();

            //Remplazar boton por input
            Parent.insertBefore(Edit, Sub);
            Sub.remove();

            //Cambio para cancelar la edicion
            function Stop(){                 
                const NewSub =  document.createElement ("button");
                
                NewSub.textContent = Sub.textContent;
                NewSub.type = 'submit';
                NewSub.id = "Edit " + id;
                Parent.insertBefore(NewSub, Edit);

                Edit.remove();
                Cancel.textContent = "Editar";
                Cancel.onclick = () => edit(id);;

                return;
            }

            Cancel.textContent = "Cancelar"; //Cambiar de texto a foto
            Cancel.onclick = Stop;

            aa = document.createElement("button")
            aa.textcontent = "confirmar"
            Cancel.append(aa)
            //Enter event
            Edit.addEventListener("keydown", function (event) {
              if (event.key === "Enter") {

                    //Crea la XMLhttpRequest
                    const Text = Edit.value;
                    const Upload = new XMLHttpRequest();

                    //Prepara la request
                    Upload.open("POST", url);
                    Upload.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
                    Upload.send("Edit[0]=" + encodeURIComponent(Text) + "& Edit[1]=" + encodeURIComponent(id));

                    window.location.reload(false);

                //Borra el input
                Edit.remove();
                Cancel.textContent = "Editar"
            }
              ;
            }, true)
        };

        function Delete(id){
            const Ask = document.createElement('div');
            Ask.id = "Ask";

            document.body.appendChild(Ask);

            const Class = document.getElementById("Ask");
            Class.className = "Ask";

            const text = document.createElement ('p');
            const Sure = document.createElement ('Button');
            const No = document.createElement ('Button');


            text.textContent = "Estas seguro que queres borrar esta rutina";

            function Borrar(){
                
                Send(id, "Delete=", true);
            };

            function Cancelar(){
                Ask.remove();
            };

            Sure.textContent = "Borrar";
            Sure.onclick = Borrar;
    
            No.textContent = "Cancelar";
            No.onclick = Cancelar;

            Ask.appendChild(text);
            Ask.appendChild(Sure);
            Ask.appendChild(No);
        };

        function href(a) {
            url = "Rutinas.php"
            Send(a, "wId=", false)
            window.location.replace("http://localhost/Tesina/Rutinas/Rutinas.php"); 
        }

        function Link(Id){
            url = "Lista.php"
            Send(Id, "id=", false)
        }

        function vinc(id) {
const vent = document.createElement("div");
const Code = document.createElement("input");
const lista = document.getElementById("Lista");

vent.className = "popup";

lista.append(vent);
vent.append(Code);

Code.focus();
console.log(id);

Code.addEventListener("keydown", async function(event) {

    if (event.key !== "Enter") {
        return;
    }

    try {

        const v = await fetch(
            "tokens.php?Code=" + encodeURIComponent(Code.value, id)
        );

        if (!v.ok) {
            throw new Error("Error HTTP: " + v.status);
        }

        const resultado = await v.json();

        const cnf = document.createElement("label");

        if (resultado === true) {
            cnf.textContent = "Vinculado";
        } else {
            cnf.textContent = "Código equivocado";
        }

        vent.append(cnf);

    } catch (error) {

        console.error(error);

        const cnf = document.createElement("label");
        cnf.textContent = "Error al conectar con el servidor";
        vent.append(cnf);
    }
});
}

    </script>


</body>
</html>
