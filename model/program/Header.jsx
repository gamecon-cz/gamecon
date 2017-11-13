//nechávam ako príklad v podobe podobnej pôvodnej - Duli

var Header = React.createClass({
    componentDidMount: function(){
      // příklad zavolání všech notifikací
      programData.notifikace.forEach(function(notifikace) { notifikace() });
    },
    render: function() {
    return (
      <h1>Program</h1>
    )
  }
})
