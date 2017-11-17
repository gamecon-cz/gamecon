class Rozvrh extends React.Component{
    constructor(props){
        super(props)
        this.vypis=this.vypis.bind(this)
    }

    filtruj(poleAktivit){
        return poleAktivit.filter(aktivita => {
            for (var i=0;i < this.props.zvoleneLinie.length;i++) {
                if (aktivita.linie == this.props.zvoleneLinie[i].id) {
                    return true;
                }
            }
            return false;
        })
    }

    vypis(){
        var vyfiltrovanePole = this.filtruj(programData.aktivity);
        var poleAktivit = vyfiltrovanePole.map((item) =>
            <div key = {item.id} >{item.nazev}</div>
        );
        return poleAktivit;
    }

    render(){
        return(
            <div>{this.vypis()}</div>
        )
    }
}
