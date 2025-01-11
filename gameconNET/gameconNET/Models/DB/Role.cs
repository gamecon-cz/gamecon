using System.ComponentModel.DataAnnotations;
using System.ComponentModel.DataAnnotations.Schema;

namespace gameconNET.Models.DB;

[Table("role_seznam")]
public class Role
{
    [Key]
    [Required]
    [Column("id_role")]
    public int Id { get; set; }

    [MaxLength(36)]
    [Required]
    [Column("kod_role")]
    public string Code { get; set; }

    [MaxLength(255)]
    [Required]
    [Column("nazev_role")]
    public string Name { get; set; }

    [Column("popis_role")]
    public string? Description { get; set; }

    [Required]
    [Column("rocnik_role")]
    public int Year { get; set; }

    [Required]
    [Column(name: "typ_role", TypeName = "varchar(24)")]
    public RoleType Type { get; set; }

    [Required]
    [Column("vyznam_role", TypeName = "varchar(48)")]
    public RoleIntent Intent { get; set; }

    [Required]
    [Column("skryta", TypeName = "tinyint(1)")]
    public bool Hidden { get; set; }

    [Required]
    [Column("kategorie_role", TypeName = "tinyint(3) unsigned")]
    public uint Category { get; set; }

    // many-to-many configured in GameConContext.OnModelCreating()
    public List<Permission> Permissions { get; } = [];
}

public enum RoleType
{
    rocnikova,
    ucast,
    trvala
}

public enum RoleIntent
{
    ZKONTROLOVANE_UDAJE,
    SOBOTNI_NOC_ZDARMA,
    BRIGADNIK,
    HERMAN,
    NEODHLASOVAT,
    NEDELNI_NOC_ZDARMA,
    STREDECNI_NOC_ZDARMA,
    DOBROVOLNIK_SENIOR,
    PARTNER,
    INFOPULT,
    ZAZEMI,
    VYPRAVEC,
    ODJEL,
    PRITOMEN,
    PRIHLASEN,
    ORGANIZATOR_ZDARMA,
    VYPRAVECSKA_SKUPINA,
    CESTNY_ORGANIZATOR,
    ADMIN,
    CFO,
    PUL_ORG_UBYTKO,
    PUL_ORG_TRICKO,
    CLEN_RADY,
    SEF_INFOPULTU,
    SEF_PROGRAMU
}
