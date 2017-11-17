class Program extends React.Component{
    constructor(props){
        super(props);
        programData.linie = this.uklidLinie(programData.linie);

        //na začátku jsou všechny linie zvolené(viditelné)
        this.state = {
            zvoleneLinie: programData.linie.slice()
        };

        this.zvolTytoLinie = this.zvolTytoLinie.bind(this);
    }

    zvolTytoLinie(linie){
        this.setState({zvoleneLinie: linie});
    }

    uklidLinie(linie){
        //Dej linie do pole a zoraď je podle pořadí
        var linieVPoli = [];
        for (var cisloLinie in linie){
            linieVPoli.push(linie[cisloLinie]);
        }
        return linieVPoli.sort((lajnaA, lajnaB) => lajnaA.poradi - lajnaB.poradi);
    }
    render(){
        return (
            <div>
                <Header/>
                    <ZvolLinie linie = {programData.linie} zvoleneLinie = {this.state.zvoleneLinie} zvolTytoLinie = {this.zvolTytoLinie}/>
                <Rozvrh zvoleneLinie = {this.state.zvoleneLinie} />
            </div>
        )
    }
}
