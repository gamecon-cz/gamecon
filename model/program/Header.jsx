//nechávam ako príklad v podobe podobnej pôvodnej - Duli

var Header = React.createClass({
  render: function() {

    // příklad zavolání všech notifikací
    programData.notifikace.forEach(function(notifikace) { notifikace() });

    return (
      <h1>Program</h1>
    )
  }
})
