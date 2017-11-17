class ZvolLinie extends React.Component{
    constructor(props){
        super(props);
    }

    //když klikáme, přepínáme jestli je linie zapnutá nebo vypnutá, měníme pole zvolených linií
    handleClick(lajna){
        var noveZvoleneLinie = this.props.zvoleneLinie.slice();

        //zjisti index lajny v poli zvolených linií. Jestli tam je, vyhoď ji. Jestli tam není, přidej jí.
        var indexLajny = this.props.zvoleneLinie.findIndex((lajnaVPoli) => {
            return lajnaVPoli.nazev == lajna.nazev;
        });
        if (indexLajny >= 0){
            noveZvoleneLinie.splice(indexLajny, 1);
        }
        else {
            noveZvoleneLinie.push(lajna);
        }
        this.props.zvolTytoLinie(noveZvoleneLinie);
    }

    render(){
        //vyfiltruj záporné pořadí
        var linie = this.props.linie.filter(lajna => lajna.poradi>0);

        //Udělej tlačítko pro každou linii
        var tlacitkaLinii = linie.map(lajna => {
            //farba pro nezvolené linie je experimentálně červená
            var styl = {backgroundColor: "#f00" };
            var index = this.props.zvoleneLinie.findIndex(lajnaVPoli => lajnaVPoli.nazev == lajna.nazev);

            //jestli je linie mezi zvolenými, uděláme jí experimentálně zelenou
            if (index>-1){
                styl = {backgroundColor: "#0f0"};
            }

            return <button onClick = {() => this.handleClick(lajna)} style = {styl}>
                {lajna.nazev.charAt(0).toUpperCase() + lajna.nazev.slice(1)}
            </button>
        });

        return (
            <div>
                {tlacitkaLinii}
            </div>
        );
    }
}
