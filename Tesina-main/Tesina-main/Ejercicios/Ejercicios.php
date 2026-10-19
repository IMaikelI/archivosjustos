<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejercicios</title>
    <link rel="stylesheet" href="../Css/Standard.css">
</head>
<body>
    </body>
    <?php   include '../Bl.php'; ?>
    <?php include '../1Rm.html'; ?>

    <div id= "Lista" class="ListaEjercicios">
    </div>

    <div id = "Side" class="Side">
        <div id = "mu">
        <input type="radio" value=0 id="pecho" name="a">
        <label for="pecho">pecho</label>

        <input type="radio" value=1 id="espalda" name="a">
        <label for="pecho">Espalda</label>

        <input type="radio" value=2 id="Bicep" name="a">
        <label for="Bicep">Bicep</label>

        <input type="radio" value=3 id="Tricep" name="a">
        <label for="Tricep">Tricep</label>

        <input type="radio" value=4 id="cuadricep" name="a" >
        <label for="cuadricep">Cuadriceps</label>

        <input type="radio" value=5 id="Isquio" name="a" >
        <label for="Isquio">Isquio</label>

        <input type="radio" value=6 id="gemelos" name="a" >
        <label for="gemelos">gemelos</label>

        </div>

        <div id = "Type">
            <input type="radio" value="Cable" id="Cable" name="b" >
            <label for="Cable">Cable</label>

            <input type="radio" value="Libre" id="Libre" name="b">
            <label for="Libre">Libre</label>
        </div>
    </div>

    <script src="../Send-Recieve/Send-Recieve.js"></script>

    <script>
        All = undefined
        Lista = document.getElementById ('Lista')
        Side = document.getElementById ('Side')
        condition = ""
        Kon = ["", "", ""]
        url = "ListaEjercicios.php"
        
        Create = () => {
                    url = "ListaEjercicios.php"
            Send(condition, "Conditions=", false);

            Get (url, function(Data){

                //borra todo
                a = document.getElementsByClassName("Cont").length
                if (a > 0) {
                    for (let i = 0; i < a; i++) {                    
                        document.getElementById("Cont"+ i).remove()
                    }
                } else{
                    //pass
                }
                

                //Lista ordenada
                const List = Object.entries(Data.Num).map(([id, Name]) => ({ id, Name })); //Obj -> Array

                    const Info = [];
                    for (let i = 0; i < List.length; i++) {
                        Info [i] = List[i].Name.split(", "); //"Name", "ISSET"
                    };
                    

                function Show(Temp, id) { //Mostrar info

                    if (All !== undefined) {
                        All.remove()
                    }

                    All = document.createElement ("div")
                    Top = document.createElement ("div")
                    info = document.createElement ("div")
                    All.className = "All"

                    Name = document.createElement ("label")
                    data = document.createElement ("ul")
                    Close = document.createElement("button")
                    Close.textContent = "x"

                    Name.textContent = Temp[0] 


                    document.body.append(All)
                    All.append(Top, info)
                    Top.append(Name, Close)
                    info.append(data)

                    Close.onclick = function(){
                        All.remove()
                    } 
                    
                        url = "recordsFetch.php"
                        
                    Send(id, "List=", false)

                    setTimeout(() => {
                        Get("recordsFetch.php", function(Records){
                        const records = Object.values(Records); //Obj -> Array
                        console.log(records[0][0]);
                        
                        
                        if (records[0][0] !== undefined) {
                            records.forEach(element => {
                                Text = document.createElement('li')

                                Text.textContent = "Fecha = " + element[1] + " Peso = " + element[0] + " Kg"

                                data.append(Text)
                            
                            });
                        } else{
                            Text = document.createElement('li')

                                Text.textContent = "no hay records"

                                data.append(Text)
                        }
                        
                    }) 

                    }, 1);
                    

                }

                let i = 0
                let b = []
                Info.forEach(items => { //Crea los botones
                    Cont = document.createElement("div")
                    Btn = document.createElement("button")
                    Cont.id = "Cont" + i 
                    Cont.className = "Cont"

                    Btn.id = "Btn " + i

                    Lista.append (Cont)
                    Cont.append (Btn)

                    const id = List[i]['id']
                    
                    Btn.textContent = items[0] 
                    Btn.onclick = function(){
                        Show(items, id)
                    };

                    i += 1       
                })
            })
        }

        
            const MuLi = document.getElementsByName("a"); //Separador x musculos
            const TypeLi = document.getElementsByName("b"); //Separador x Tipo
                
            Side.addEventListener('click', function(event){

                //Musculo
                if (document.getElementById("mu").contains(event.target)) { 
                    for (let i = 0; i < 7; i++) {

                        if (MuLi[i].contains(event.target) && Kon[0].includes(MuLi[i].value)){ //Uncheck
                            MuLi[i].checked = false
                            Kon[0] = ""
                        }
                        
                        if (MuLi[i].checked){ //Add
                            Kon[0] = "Mu = " + MuLi[i].value + " "
                        } 
                        
                        
                    }
                }

                //Tipo
                if (document.getElementById("Type").contains(event.target)) {
                    for (let i = 0; i < 2; i++) {

                        if (TypeLi[i].contains(event.target) && Kon[1].includes(TypeLi[i].value)){ //Uncheck
                            TypeLi[i].checked = false
                            Kon[1] = ""
                        }

                        if (TypeLi[i].checked){ //Add
                            Kon[1] = "Type = '" + TypeLi[i].value + "' "
                        }                  
                    }   
                }

                temp = []

                
                
                for (let i = 0; i < Kon.length; i++) {
                    if (Kon[i] == "") {
                        //Skip
                    } else{
                        temp.push(Kon[i]) 
                    }
                    
                }
                    
                if (temp.length > 0) {
                   condition = "WHERE " + temp[0]

                   for (let i = 1; i < temp.length; i++) {
                    condition += "AND " + temp[i]
                   }
                } else{
                    condition = ""
                }
            
        //Crea todo    
        Create()
        })
                
            
            //Crea todo (solo 1 vez)
            Create()

    </script>
    
</html>